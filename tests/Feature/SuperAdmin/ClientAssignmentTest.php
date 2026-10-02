<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\EmploymentType;
use App\Models\Client;
use App\Models\ClientAssignmentRequest;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ClientAssigned;
use App\Notifications\ClientAssignmentDecided;
use App\Notifications\ClientAssignmentRequested;
use App\Services\Settings\SystemRules;
use App\Services\TimeTracking\TimerBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * On Scheduling → Clients only the Admin adds clients: they type the client's name and choose
 * Full-Time or Part-Time. The Super Admin only approves (setting the rate) or rejects.
 */
class ClientAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $contractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 10:00:00');
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->admin = User::factory()->admin()->create();
        $this->contractor = User::factory()->create(['employment_type' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rate(array $overrides = []): array
    {
        return [
            'gross_pay' => 20000,
            'pay_frequency' => 'semi_monthly',
            'working_days' => 20,
            'hours_per_day' => 8,
            'effective_date' => '2026-09-28',
            ...$overrides,
        ];
    }

    private function submit(string $client = 'Aurora Dental', string $type = 'full_time', ?User $contractor = null): ClientAssignmentRequest
    {
        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.clients.store'), [
                'employee_id' => ($contractor ?? $this->contractor)->id,
                'client_name' => $client,
                'employment_type' => $type,
                'start_date' => '2026-09-28',
            ])
            ->assertSessionHasNoErrors();

        return ClientAssignmentRequest::query()->latest('id')->first();
    }

    private function approve(ClientAssignmentRequest $request): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.requests.clients.approve', $request), $this->rate())
            ->assertSessionHasNoErrors();
    }

    public function test_the_admin_scheduling_module_opens_on_the_clients_tab(): void
    {
        Client::factory()->create(['client_name' => 'Northline']);

        $this->actingAs($this->admin)
            ->get(route('admin.scheduling.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Scheduling/Clients')
                ->where('clientNames', ['Northline'])
                ->where('hours', ['full_time' => 8, 'part_time' => 4])
                ->has('employmentTypes', 2));

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'navigation',
                fn ($items) => collect($items)->firstWhere('key', 'scheduling')['routeName'] === 'admin.scheduling.clients.index'
            ));
    }

    public function test_a_new_client_typed_by_the_admin_waits_for_the_super_admin(): void
    {
        Notification::fake();

        $request = $this->submit('  Aurora   Dental ');

        $this->assertTrue($request->isPending());
        $this->assertNull($request->client_id);
        $this->assertSame('Aurora Dental', $request->client_name);
        $this->assertSame(EmploymentType::FullTime, $request->employment_type);
        $this->assertDatabaseCount(Client::class, 0); // created only once approved
        Notification::assertSentTo($this->superAdmin, ClientAssignmentRequested::class);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.requests', ['type' => 'clients']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('assignments.0.client.name', 'Aurora Dental')
                ->where('assignments.0.client.isNew', true)
                ->where('assignments.0.employmentTypeLabel', 'Full-Time')
                ->where('rateDefaults.partTimeHours', 4));
    }

    public function test_approving_creates_the_client_and_it_appears_for_the_contractor(): void
    {
        Notification::fake();
        $request = $this->submit();

        $this->approve($request);

        $client = Client::query()->sole();
        $this->assertSame('Aurora Dental', $client->client_name);
        $this->assertSame('CL-0001', $client->client_code);
        $this->assertTrue($client->is_active);

        $rate = $this->contractor->currentRates()->sole();
        $this->assertSame($client->id, $rate->client_id);
        $this->assertSame(EmploymentType::FullTime, $rate->employment_type);
        // Working days and Full-Time hours come from System & Rules (11 days, 8 hours), whatever the form sent.
        $this->assertSame(11, $rate->working_days);
        $this->assertSame(8, $rate->hours_per_day);
        $this->assertSame(round(20000 / (11 * 8), 2), round($rate->hourlyRate(), 2));
        $this->assertSame($client->id, $request->fresh()->client_id);

        Notification::assertSentTo($this->contractor, ClientAssigned::class);
        Notification::assertSentTo($this->admin, ClientAssignmentDecided::class);

        $this->actingAs($this->admin)
            ->get(route('admin.scheduling.clients.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('assignments.0.client.name', 'Aurora Dental')
                ->where('assignments.0.type', 'Full-Time')
                ->where('assignments.0.schedules', 0));
    }

    public function test_each_schedule_has_its_own_break_allowance(): void
    {
        Notification::fake();
        $this->approve($this->submit('Aurora Dental'));
        $this->approve($this->submit('Northline', 'part_time'));

        foreach (['Aurora Dental' => [30, '09:00'], 'Northline' => [45, '18:00']] as $client => [$break, $start]) {
            $this->actingAs($this->admin)->post(route('admin.scheduling.store'), [
                'employee_id' => $this->contractor->id,
                'client_id' => Client::named($client)->id,
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'start_time' => $start,
                'end_time' => '23:59',
                'break_allowance_minutes' => $break,
                'start_date' => '2026-09-25',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)
            ->get(route('admin.scheduling.index', ['client' => Client::named('Northline')->id]))
            ->assertInertia(fn (Assert $page) => $page->where('schedules.data.0.breakAllowance', 45));

        $cards = collect(app(TimerBoard::class)->for($this->contractor)['clients'])->pluck('breakAllowance', 'name');
        $this->assertSame(30, $cards['Aurora Dental']);
        $this->assertSame(45, $cards['Northline']);

        $this->actingAs($this->admin)->post(route('admin.scheduling.store'), [
            'employee_id' => $this->contractor->id,
            'client_id' => Client::named('Aurora Dental')->id,
            'working_days' => ['sat'],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_allowance_minutes' => 500,
            'start_date' => '2026-09-25',
        ])->assertSessionHasErrors('break_allowance_minutes');
    }

    public function test_approval_takes_hours_from_the_client_type_and_refuses_hourly_pay(): void
    {
        Notification::fake();
        app(SystemRules::class)->update(['default_working_days' => 10, 'part_time_hours' => 4], $this->superAdmin);
        $request = $this->submit('Northline', 'part_time');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.requests.clients.approve', $request), $this->rate(['pay_frequency' => 'hourly']))
            ->assertSessionHasErrors('pay_frequency');

        $this->approve($request);

        $rate = $this->contractor->currentRates()->sole();
        $this->assertSame(10, $rate->working_days);
        $this->assertSame(4, $rate->hours_per_day);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.requests', ['type' => 'clients']))
            ->assertInertia(fn (Assert $page) => $page->where('payFrequencies', fn ($options) => ! collect($options)->contains('value', 'hourly')));
    }

    public function test_a_known_client_name_is_reused_whatever_the_case(): void
    {
        $existing = Client::factory()->create(['client_name' => 'Aurora Dental']);

        $request = $this->submit('aurora dental');
        $this->assertSame($existing->id, $request->client_id);

        $this->approve($request);

        $this->assertDatabaseCount(Client::class, 1);
        $this->assertSame($existing->id, $this->contractor->currentRates()->sole()->client_id);
    }

    public function test_one_full_time_client_makes_the_contractor_full_time(): void
    {
        $this->approve($this->submit('Northline', 'part_time'));
        $this->assertSame(EmploymentType::PartTime, $this->contractor->fresh()->employment_type);

        $this->approve($this->submit('Harbor Vet', 'part_time'));
        $this->assertSame(EmploymentType::PartTime, $this->contractor->fresh()->employment_type);

        $this->approve($this->submit('Aurora Dental', 'full_time'));
        $this->assertSame(EmploymentType::FullTime, $this->contractor->fresh()->employment_type);
        $this->assertCount(3, $this->contractor->currentRates);

        // Ending the Full-Time client leaves only Part-Time ones.
        $fullTime = $this->contractor->currentRates()->where('employment_type', 'full_time')->sole();
        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.workforce.employees.rates.destroy', [$this->contractor, $fullTime]))
            ->assertSessionHasNoErrors();

        $this->assertSame(EmploymentType::PartTime, $this->contractor->fresh()->employment_type);
    }

    public function test_the_schedule_length_follows_the_client_type(): void
    {
        $this->approve($this->submit('Aurora Dental', 'full_time'));
        $this->approve($this->submit('Northline', 'part_time'));
        [$fullTime, $partTime] = [Client::named('Aurora Dental'), Client::named('Northline')];

        $schedule = fn (Client $client, string $start) => [
            'employee_id' => $this->contractor->id,
            'client_id' => $client->id,
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'start_time' => $start,
            'end_time' => '23:59',
            'break_allowance_minutes' => 60,
            'start_date' => '2026-09-28',
        ];

        $this->actingAs($this->admin)->post(route('admin.scheduling.store'), $schedule($fullTime, '09:00'))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.scheduling.store'), $schedule($partTime, '18:00'))->assertSessionHasNoErrors();

        $this->assertSame('17:00:00', Schedule::query()->where('client_id', $fullTime->id)->sole()->end_time);
        $this->assertSame('22:00:00', Schedule::query()->where('client_id', $partTime->id)->sole()->end_time);
        $this->assertSame('Contractor', Schedule::query()->first()->job_position);

        // The hours come from System & Rules.
        app(SystemRules::class)->update(['part_time_hours' => 5], $this->superAdmin);
        $other = User::factory()->create();
        $this->approve($this->submit('Northline', 'part_time', $other));
        $this->actingAs($this->admin)->post(route('admin.scheduling.store'), [...$schedule($partTime, '20:00'), 'employee_id' => $other->id])->assertSessionHasNoErrors();

        $this->assertSame('01:00:00', Schedule::query()->where('employee_id', $other->id)->sole()->end_time);
    }

    public function test_rejecting_changes_nothing_and_tells_the_admin_why(): void
    {
        Notification::fake();
        $request = $this->submit();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.requests.clients.reject', $request), ['note' => 'Aurora needs a full-time VA.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ClientAssignmentRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertDatabaseCount(Rate::class, 0);
        $this->assertDatabaseCount(Client::class, 0);
        Notification::assertSentTo($this->admin, ClientAssignmentDecided::class, fn ($notification) => str_contains($notification->toArray($this->admin)['message'], 'full-time VA'));
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $request = $this->submit();
        $this->actingAs($this->superAdmin)->post(route('super-admin.requests.clients.reject', $request));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.requests.clients.approve', $request), $this->rate())
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount(Rate::class, 0);
    }

    public function test_the_same_client_cannot_be_given_twice(): void
    {
        $this->submit();

        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.clients.store'), ['employee_id' => $this->contractor->id, 'client_name' => 'AURORA DENTAL', 'employment_type' => 'part_time', 'start_date' => '2026-09-28'])
            ->assertSessionHasErrors('client_name');

        $this->approve(ClientAssignmentRequest::query()->sole());

        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.clients.store'), ['employee_id' => $this->contractor->id, 'client_name' => 'Aurora Dental', 'employment_type' => 'full_time', 'start_date' => '2026-10-01'])
            ->assertSessionHasErrors('client_name');

        $this->assertDatabaseCount(ClientAssignmentRequest::class, 1);
    }

    public function test_the_client_name_and_type_are_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.scheduling.clients.store'), ['employee_id' => $this->contractor->id, 'client_name' => '', 'employment_type' => 'sometimes', 'start_date' => '2026-09-28'])
            ->assertSessionHasErrors(['client_name', 'employment_type']);
    }

    public function test_the_admin_can_withdraw_a_waiting_request(): void
    {
        $request = $this->submit();

        $this->actingAs($this->admin)->post(route('admin.scheduling.clients.cancel', $request))->assertSessionHasNoErrors();

        $this->assertSame(ClientAssignmentRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    public function test_only_the_super_admin_approves_and_sets_rates(): void
    {
        $request = $this->submit();

        $this->actingAs($this->admin)
            ->post(route('super-admin.requests.clients.approve', $request), $this->rate())
            ->assertForbidden();

        $this->assertFalse(Route::has('super-admin.workforce.employees.rates.store'));
        $this->assertDatabaseCount(Rate::class, 0);
    }

    public function test_waiting_assignments_show_on_the_super_admin_dashboard(): void
    {
        $this->submit();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingApprovals.items.4.key', 'client_assignments')
                ->where('pendingApprovals.items.4.count', 1));
    }
}
