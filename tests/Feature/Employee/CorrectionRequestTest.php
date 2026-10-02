<?php

namespace Tests\Feature\Employee;

use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Notifications\AttendanceCorrected;
use App\Notifications\CorrectionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missed_clock_out_becomes_a_pending_request_that_admins_are_notified_about(): void
    {
        Notification::fake();
        $this->travelTo('2026-09-25 10:00:00');

        $employee = User::factory()->create();
        $admins = User::factory()->admin()->count(2)->create();
        $attendance = Attendance::factory()->for($employee, 'employee')->status(AttendanceStatus::Incomplete)->create([
            'date' => '2026-09-24',
            'time_in' => '2026-09-24 09:00:00',
            'time_out' => null,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.attendance.corrections.store'), [
                'attendance_id' => $attendance->id,
                'date' => '2026-09-24',
                'time_out' => '18:00',
                'reason' => 'Forgot to stop my timer.',
            ])
            ->assertSessionHasNoErrors();

        $correction = AttendanceCorrection::sole();
        $this->assertSame(CorrectionStatus::Pending, $correction->status);
        $this->assertSame('employee', $correction->source);
        $this->assertSame('time_out', $correction->field_corrected);
        $this->assertSame('09:00', $correction->original_time_in);
        $this->assertNull($attendance->fresh()->time_out, 'Attendance only changes once approved.');

        Notification::assertSentTo($admins, CorrectionRequested::class);

        $this->get(route('employee.attendance.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Attendance')
                ->where('corrections.0.status', 'pending')
                ->where('summary.incomplete', 1)
            );
    }

    public function test_a_second_request_for_the_same_day_waits_for_the_first(): void
    {
        $employee = User::factory()->create();
        $payload = ['date' => now()->subDay()->toDateString(), 'time_in' => '09:00', 'reason' => 'Clock issue'];

        $this->actingAs($employee)->post(route('employee.attendance.corrections.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('employee.attendance.corrections.store'), $payload)->assertSessionHasErrors('date');
    }

    public function test_employees_cannot_request_changes_to_someone_elses_attendance(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('employee.attendance.corrections.store'), [
                'attendance_id' => Attendance::factory()->create()->id,
                'date' => now()->toDateString(),
                'time_in' => '09:00',
                'reason' => 'x',
            ])
            ->assertSessionHasErrors('attendance_id');
    }

    public function test_the_employee_is_notified_when_an_admin_decides(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $correction = AttendanceCorrection::factory()->create(['status' => CorrectionStatus::Pending, 'source' => 'employee']);

        $this->actingAs($admin)
            ->post(route('admin.attendance.requests.reject', $correction), ['remarks' => 'Timer data shows 9:30.'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($correction->employee, AttendanceCorrected::class);
    }

    public function test_an_admin_never_reviews_their_own_request(): void
    {
        $admin = User::factory()->admin()->create();
        $own = AttendanceCorrection::factory()->for($admin, 'employee')->create(['status' => CorrectionStatus::Pending, 'source' => 'employee']);

        $this->actingAs($admin)->post(route('admin.attendance.requests.approve', $own))->assertForbidden();

        $this->get(route('admin.attendance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('requests', 0));
    }
}
