<?php

namespace Tests\Feature\Employee;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\TimeLogStatus;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\TimeLog;
use App\Models\User;
use App\Notifications\TimerStoppedAtShiftEnd;
use App\Services\Settings\SystemRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TimeTrackerTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Friday 25 Sep 2026
        $this->travelTo('2026-09-25 08:55:00');

        $this->employee = User::factory()->create();
        $this->client = Client::factory()->create(['client_name' => 'Aurora Dental']);
        Rate::factory()->for($this->employee, 'employee')->for($this->client)->create();
        Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create(['job_position' => 'Patient Coordinator']);
    }

    public function test_the_board_shows_one_card_per_assigned_client(): void
    {
        $this->actingAs($this->employee)
            ->get(route('employee.time-tracker.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/TimeTracker')
                ->has('board.clients', 1)
                ->where('board.clients.0.name', 'Aurora Dental')
                ->where('board.clients.0.position', 'Patient Coordinator')
                ->where('board.clients.0.status', 'not_started')
                ->where('board.clients.0.scheduled.label', '9:00 AM – 6:00 PM')
                ->where('board.scheduledMinutes', 540)
            );
    }

    public function test_a_timer_runs_takes_a_break_and_stops_into_attendance(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id])
            ->assertSessionHasNoErrors();

        $log = TimeLog::sole();
        $this->assertSame(TimeLogStatus::Running, $log->status);
        $this->assertSame(AttendanceStatus::Incomplete, Attendance::sole()->status);

        $this->travelTo('2026-09-25 12:00:00');
        $this->post(route('employee.time-tracker.break', $log));
        $this->assertSame(TimeLogStatus::OnBreak, $log->fresh()->status);

        $this->travelTo('2026-09-25 12:45:00');
        $this->post(route('employee.time-tracker.break', $log));
        $this->assertSame(45, $log->fresh()->break_minutes);

        $this->travelTo('2026-09-25 18:00:00');
        $this->post(route('employee.time-tracker.stop', $log));

        $log->refresh();
        $this->assertSame(TimeLogStatus::Completed, $log->status);
        $this->assertSame('8.33', $log->total_hours); // 9h05m on the clock − 45m break

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame('8.25', $attendance->actual_hours); // the 5 minutes before the 9:00 AM start are not counted
        $this->assertSame(45, $attendance->break_minutes);
    }

    public function test_starting_after_the_scheduled_time_is_late_with_no_grace_period(): void
    {
        $this->travelTo('2026-09-25 09:01:00');

        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id]);

        $this->assertSame(1, Attendance::sole()->late_minutes);
    }

    public function test_several_client_timers_can_run_at_once_and_stop_together(): void
    {
        $second = Client::factory()->create();
        Rate::factory()->for($this->employee, 'employee')->for($second)->create();
        Schedule::factory()->for($this->employee, 'employee')->for($second)->create();

        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id]);
        $this->post(route('employee.time-tracker.start'), ['client_id' => $second->id]);

        $this->assertSame(2, TimeLog::query()->open()->count());

        $this->travelTo('2026-09-25 10:00:00');
        $this->post(route('employee.time-tracker.stop-all'));

        $this->assertSame(0, TimeLog::query()->open()->count());
        $this->assertSame(2, Attendance::count());
    }

    public function test_the_same_client_cannot_have_two_running_timers(): void
    {
        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id]);

        $this->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id])
            ->assertSessionHasErrors('client_id');
    }

    public function test_timers_only_start_for_assigned_clients(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.time-tracker.start'), ['client_id' => Client::factory()->create()->id])
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, TimeLog::count());
    }

    public function test_employees_cannot_touch_someone_elses_timer(): void
    {
        $log = TimeLog::factory()->create();

        $this->actingAs($this->employee)->post(route('employee.time-tracker.stop', $log))->assertNotFound();
        $this->assertTrue($log->fresh()->isOpen());
    }

    public function test_a_timer_after_midnight_belongs_to_last_nights_graveyard_shift(): void
    {
        $night = Client::factory()->create();
        Rate::factory()->for($this->employee, 'employee')->for($night)->create();
        Schedule::factory()->graveyard()->for($this->employee, 'employee')->for($night)->create();

        // Saturday 1:00 AM, inside Friday's 10 PM – 6 AM shift.
        $this->travelTo('2026-09-26 01:00:00');

        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $night->id]);

        $this->assertSame('2026-09-25', TimeLog::sole()->date->toDateString());
    }

    public function test_an_admin_tracks_their_own_time_too(): void
    {
        $admin = User::factory()->admin()->create();
        Rate::factory()->for($admin, 'employee')->for($this->client)->create();
        Schedule::factory()->for($admin, 'employee')->for($this->client)->create();

        $this->actingAs($admin)
            ->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($admin->id, TimeLog::sole()->employee_id);
    }

    public function test_timers_go_back_to_zero_once_the_days_payroll_is_released(): void
    {
        TimeLog::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'date' => '2026-09-25',
            'time_in' => '2026-09-25 09:00:00',
            'time_out' => '2026-09-25 10:00:00',
            'status' => TimeLogStatus::Completed,
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.time-tracker.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.combinedSeconds', 3600)
                ->where('board.clients.0.completedSeconds', 3600)
                ->where('board.locked', false));

        PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Released]); // Sep 11 – 25

        $this->actingAs($this->employee)
            ->get(route('employee.time-tracker.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.combinedSeconds', 0)
                ->where('board.clients.0.completedSeconds', 0)
                ->where('board.clients.0.status', 'not_started')
                ->where('board.clients.0.locked', true)
                ->where('board.locked', true)
                // The session is still listed, marked as paid out.
                ->where('board.today.0.paidOut', true));

        $this->actingAs($this->employee)
            ->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id])
            ->assertSessionHasErrors('client_id');
    }

    public function test_each_card_shows_whether_that_client_is_full_time_or_part_time(): void
    {
        $this->employee->update(['employment_type' => EmploymentType::FullTime]);
        $this->employee->currentRates()->update(['employment_type' => EmploymentType::PartTime->value]);

        $this->actingAs($this->employee)
            ->get(route('employee.time-tracker.index'))
            ->assertInertia(fn (Assert $page) => $page->where('board.clients.0.employmentType', 'Part-Time'));
    }

    public function test_a_timer_still_running_at_the_end_of_the_shift_stops_at_the_scheduled_end(): void
    {
        Notification::fake();

        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id]);

        // The contractor forgets to stop; the shift ends at 6:00 PM.
        $this->travelTo('2026-09-25 19:30:00');
        $this->artisan('arka:stop-finished-shifts')->assertSuccessful();

        $log = TimeLog::sole();
        $this->assertSame(TimeLogStatus::Completed, $log->status);
        $this->assertSame('2026-09-25 18:00:00', $log->time_out->format('Y-m-d H:i:s'));
        Notification::assertSentTo($this->employee, TimerStoppedAtShiftEnd::class);
    }

    public function test_opening_the_time_tracker_also_stops_finished_shifts_and_the_card_starts_from_zero(): void
    {
        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id]);

        $this->travelTo('2026-09-25 18:30:00');

        $this->actingAs($this->employee)
            ->get(route('employee.time-tracker.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.clients.0.timer', null)
                ->where('board.clients.0.completedSeconds', 0)
                ->where('board.clients.0.status', 'shift_ended')
                ->where('board.combinedSeconds', 0)
                // The session itself is kept.
                ->where('board.today.0.status', 'completed')
                ->where('board.today.0.end', '6:00 PM'));
    }

    public function test_the_timer_starts_at_most_ten_minutes_before_the_shift_and_never_after_it(): void
    {
        $start = fn () => $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id]);

        // The 9:00 AM shift opens for the timer at 8:50 AM (System & Rules).
        $this->travelTo('2026-09-25 08:49:00');
        $start()->assertSessionHasErrors(['client_id' => 'Your shift starts at 9:00 AM. You can start the timer from 8:50 AM.']);

        // After 6:00 PM the shift is over: overtime goes through a ticket.
        $this->travelTo('2026-09-25 18:00:00');
        $start()->assertSessionHasErrors('client_id');

        $this->assertSame(0, TimeLog::count());

        $this->travelTo('2026-09-25 08:50:00');
        $start()->assertSessionHasNoErrors();
        $this->assertSame(0, Attendance::sole()->late_minutes);

        // The 10 early minutes are not paid: work counts from the 9:00 AM start.
        $this->travelTo('2026-09-25 10:00:00');
        $this->post(route('employee.time-tracker.stop-all'));
        $this->assertSame('1.00', Attendance::sole()->actual_hours);
    }

    public function test_the_early_start_allowance_comes_from_system_rules_and_needs_a_shift(): void
    {
        app(SystemRules::class)->update(['timer_early_start_minutes' => 30], User::factory()->superAdmin()->create());

        $this->travelTo('2026-09-25 08:30:00');
        $this->actingAs($this->employee)->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id])->assertSessionHasNoErrors();

        // No shift on Saturday for this client.
        $this->travelTo('2026-09-26 10:00:00');
        $this->post(route('employee.time-tracker.stop-all'));
        $this->post(route('employee.time-tracker.start'), ['client_id' => $this->client->id])
            ->assertSessionHasErrors(['client_id' => 'You have no shift for this client today, so the timer cannot be started. Your administrator sets your schedule.']);
    }
}
