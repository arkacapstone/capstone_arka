<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 10:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->employee = User::factory()->create();
        $this->client = Client::factory()->create();
        Rate::factory()->for($this->employee, 'employee')->for($this->client)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_allowance_minutes' => 60,
            'start_date' => '2026-09-28',
            ...$overrides,
        ];
    }

    public function test_admin_creates_a_schedule_and_the_employee_is_notified(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        $schedule = Schedule::query()->sole();

        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], $schedule->working_days);
        $this->assertTrue($schedule->crossesMidnight());
        $this->assertSame(8.0, $schedule->expectedHours());
        Notification::assertSentTo($this->employee, ScheduleChanged::class);
    }

    public function test_a_schedule_needs_a_client_the_super_admin_approved(): void
    {
        $unassigned = Client::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.store'), $this->payload(['client_id' => $unassigned->id]))
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount(Schedule::class, 0);
        $this->assertDatabaseCount('client_assignment_requests', 0);
    }

    public function test_schedules_have_no_job_position_to_fill_in(): void
    {
        $this->actingAs($this->admin)->post(route('admin.scheduling.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('Contractor', Schedule::query()->sole()->job_position);
    }

    public function test_changing_a_schedule_keeps_the_old_one_as_history(): void
    {
        $current = Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'start_date' => '2026-09-01',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.scheduling.update', $current), [
                ...collect($this->payload(['start_time' => '21:00', 'end_time' => '05:00']))->except(['employee_id', 'start_date'])->all(),
                'effective_date' => '2026-10-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', $current->refresh()->end_date->toDateString());
        $this->assertSame('22:00:00', $current->start_time);

        $new = Schedule::query()->whereKeyNot($current->id)->sole();
        $this->assertSame('21:00:00', $new->start_time);
        $this->assertSame('2026-10-01', $new->start_date->toDateString());
    }

    public function test_a_schedule_that_has_not_started_is_edited_in_place(): void
    {
        $upcoming = Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create(['start_date' => '2026-10-05']);

        $this->actingAs($this->admin)
            ->put(route('admin.scheduling.update', $upcoming), [
                ...collect($this->payload(['start_time' => '07:00', 'end_time' => '15:00']))->except(['employee_id', 'start_date'])->all(),
                'effective_date' => '2026-10-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount(Schedule::class, 1);
        $this->assertSame('07:00:00', $upcoming->refresh()->start_time);
    }

    public function test_overlapping_schedules_save_with_a_quiet_warning(): void
    {
        $other = Client::factory()->create(['client_name' => 'Northline']);
        Rate::factory()->for($this->employee, 'employee')->for($other)->create();
        Schedule::factory()->for($this->employee, 'employee')->for($other)->create(['start_time' => '23:00:00', 'end_time' => '03:00:00']);

        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn (string $warning) => str_contains($warning, 'Northline'));

        $this->assertDatabaseCount(Schedule::class, 2);
    }

    public function test_admin_deactivates_a_schedule(): void
    {
        $schedule = Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.scheduling.status', $schedule), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $schedule->refresh()->status);
    }
}
