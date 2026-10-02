<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\PayrollPeriod;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AttendanceCorrected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 10:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Ops Admin']);
        $this->employee = User::factory()->create();
        $this->client = Client::factory()->create();
        Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create();
    }

    public function test_attendance_list_and_summary_for_a_date_range(): void
    {
        Attendance::factory()->for($this->employee, 'employee')->for($this->client)->status(AttendanceStatus::Late)->create(['date' => '2026-09-24']);
        Attendance::factory()->for($this->employee, 'employee')->for($this->client)->status(AttendanceStatus::Present)->create(['date' => '2026-09-25']);

        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index', ['from' => '2026-09-24', 'to' => '2026-09-25']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Index')
                ->has('records.data', 2)
                ->where('summary.late', 1)
                ->where('summary.present', 1)
            );
    }

    public function test_fixing_a_missing_clock_out_recalculates_and_keeps_the_original(): void
    {
        Notification::fake();

        $attendance = Attendance::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'date' => '2026-09-24',
            'time_in' => '2026-09-24 09:05:00',
            'time_out' => null,
            'status' => AttendanceStatus::Incomplete,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.correct'), [
                'attendance_id' => $attendance->id,
                'employee_id' => $this->employee->id,
                'date' => '2026-09-24',
                'time_out' => '18:00',
                'reason' => 'Forgot to clock out; confirmed with activity log.',
            ])
            ->assertSessionHasNoErrors();

        $attendance->refresh();
        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame(5, $attendance->late_minutes);
        $this->assertSame('18:00', $attendance->time_out->format('H:i'));

        $correction = AttendanceCorrection::query()->sole();
        $this->assertSame('admin', $correction->source);
        $this->assertSame('09:05', $correction->original_time_in);
        $this->assertNull($correction->original_time_out);
        $this->assertSame($this->admin->id, $correction->reviewed_by);
        Notification::assertSentTo($this->employee, AttendanceCorrected::class);
    }

    public function test_locked_payroll_days_cannot_be_corrected(): void
    {
        PayrollPeriod::factory()->create(['start_date' => '2026-09-11', 'end_date' => '2026-09-25', 'status' => PayrollPeriodStatus::Locked]);
        $attendance = Attendance::factory()->for($this->employee, 'employee')->for($this->client)->create(['date' => '2026-09-24', 'time_in' => '2026-09-24 09:00:00']);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.correct'), [
                'attendance_id' => $attendance->id,
                'employee_id' => $this->employee->id,
                'date' => '2026-09-24',
                'time_out' => '18:00',
                'reason' => 'Late fix',
            ])
            ->assertSessionHasErrors('time_in');

        $this->assertDatabaseCount(AttendanceCorrection::class, 0);
    }

    public function test_approving_an_employee_request_applies_it(): void
    {
        Notification::fake();

        $attendance = Attendance::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'date' => '2026-09-24',
            'time_in' => '2026-09-24 09:00:00',
            'status' => AttendanceStatus::Incomplete,
        ]);
        $request = AttendanceCorrection::factory()->for($this->employee, 'employee')->create([
            'attendance_id' => $attendance->id,
            'date' => '2026-09-24',
            'requested_time_out' => '18:00',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.requests.approve', $request))
            ->assertSessionHasNoErrors();

        $this->assertSame(CorrectionStatus::Approved, $request->refresh()->status);
        $this->assertSame(AttendanceStatus::Present, $attendance->refresh()->status);
        Notification::assertSentTo($this->employee, AttendanceCorrected::class);
    }

    public function test_closing_a_request_needs_remarks_and_leaves_attendance_unchanged(): void
    {
        $request = AttendanceCorrection::factory()->for($this->employee, 'employee')->create(['date' => '2026-09-24']);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.requests.reject', $request))
            ->assertSessionHasErrors('remarks');

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.requests.reject', $request), ['remarks' => 'The activity log shows no work that day.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(CorrectionStatus::Rejected, $request->refresh()->status);
        $this->assertDatabaseCount(Attendance::class, 0);
    }

    public function test_a_request_can_only_be_reviewed_once(): void
    {
        $request = AttendanceCorrection::factory()->for($this->employee, 'employee')->create([
            'date' => '2026-09-24',
            'status' => CorrectionStatus::Rejected,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.requests.approve', $request))
            ->assertSessionHasErrors('status');
    }
}
