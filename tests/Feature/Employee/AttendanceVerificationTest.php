<?php

namespace Tests\Feature\Employee;

use App\Enums\CorrectionStatus;
use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceVerification;
use App\Models\Client;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\TimeLog;
use App\Models\User;
use App\Notifications\AttendanceVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $contractor;

    private Client $client;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 10:00:00');
        $this->contractor = User::factory()->create();
        $this->client = Client::factory()->create();

        Rate::factory()->for($this->contractor, 'employee')->for($this->client)->create([
            'gross_pay' => 8000,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'working_days' => 11,
            'hours_per_day' => 8,
            'effective_date' => '2026-01-01',
        ]);

        // Sep 11 – Sep 25, opened for verification this morning.
        $this->period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Verification, 'verification_opened_at' => now()->setTime(8, 0)]);
    }

    private function attendance(string $date): Attendance
    {
        return Attendance::factory()->for($this->contractor, 'employee')->for($this->client)->create([
            'date' => $date,
            'time_in' => "{$date} 09:00:00",
            'time_out' => null,
        ]);
    }

    public function test_the_contractor_sees_the_period_to_verify(): void
    {
        $this->attendance('2026-09-15');

        $this->actingAs($this->contractor)
            ->get(route('employee.attendance.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('verification.periodId', $this->period->id)
                ->where('verification.verifiedAt', null)
                ->has('verification.records', 1)
            );
    }

    public function test_any_number_of_days_can_be_fixed_on_the_day_verification_opens(): void
    {
        $first = $this->attendance('2026-09-15');
        $second = $this->attendance('2026-09-16');

        $fix = fn (Attendance $day, string $out, string $reason) => $this->actingAs($this->contractor)
            ->post(route('employee.attendance.verification.fix', $this->period), ['attendance_id' => $day->id, 'time_out' => $out, 'reason' => $reason]);

        $fix($first, '18:00', 'Forgot to stop my timer.')->assertSessionHasNoErrors();
        $fix($second, '17:30', 'Same here.')->assertSessionHasNoErrors();
        $fix($first, '18:30', 'Actually stayed later.')->assertSessionHasNoErrors();

        $this->assertSame('18:30', $first->fresh()->time_out->format('H:i'), 'Fixes are applied without Admin review.');
        $this->assertSame('17:30', $second->fresh()->time_out->format('H:i'));

        $verification = AttendanceVerification::sole();
        $this->assertCount(3, $verification->corrections);
        $this->assertTrue($verification->corrections->every(fn (AttendanceCorrection $correction) => $correction->status === CorrectionStatus::Approved));
        // Each fix keeps the time it replaced.
        $this->assertSame('18:00', substr($verification->corrections->last()->original_time_out, 0, 5));

        $this->get(route('employee.attendance.index'))->assertInertia(fn (Assert $page) => $page
            ->where('verification.canFix', true)
            ->has('verification.fixes', 3)
            ->where('verification.fixes.2.attendanceId', $first->id)
            ->where('verification.fixes.2.before', '9:00 AM – 6:00 PM')
            ->where('verification.fixes.2.after', '9:00 AM – 6:30 PM'));
    }

    public function test_fixing_closes_at_midnight_of_the_day_verification_opened(): void
    {
        $day = $this->attendance('2026-09-15');

        $this->travelTo('2026-09-27 00:00:01');

        $this->actingAs($this->contractor)
            ->post(route('employee.attendance.verification.fix', $this->period), [
                'attendance_id' => $day->id,
                'time_out' => '18:00',
                'reason' => 'Next day.',
            ])
            ->assertSessionHasErrors('time_in');

        $this->assertNull($day->fresh()->time_out);
        $this->get(route('employee.attendance.index'))->assertInertia(fn (Assert $page) => $page->where('verification.canFix', false));

        // Submitting as verified is still possible after fixing has closed.
        $this->post(route('employee.attendance.verification.submit', $this->period))->assertSessionHasNoErrors();
    }

    public function test_submitting_notifies_the_admins_but_not_the_super_admin(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $day = $this->attendance('2026-09-15');

        $this->actingAs($this->contractor)->post(route('employee.attendance.verification.fix', $this->period), [
            'attendance_id' => $day->id,
            'time_out' => '18:00',
            'reason' => 'Forgot to stop my timer.',
        ]);
        $this->post(route('employee.attendance.verification.submit', $this->period))->assertSessionHasNoErrors();

        // The Admin reviews the changes; the Super Admin only hears once the Admin submits the period.
        Notification::assertSentTo($admin, AttendanceVerified::class, function (AttendanceVerified $notification) use ($admin) {
            $data = $notification->toArray($admin);

            return str_contains($data['message'], $this->contractor->name)
                && str_contains($data['message'], '1 fix made')
                && $data['url'] === route('admin.verification.index', absolute: false);
        });
        Notification::assertNotSentTo($superAdmin, AttendanceVerified::class);
        Notification::assertNotSentTo($this->contractor, AttendanceVerified::class);
    }

    public function test_the_super_admin_only_sees_who_submitted_without_the_changes(): void
    {
        $day = $this->attendance('2026-09-15');
        $other = User::factory()->create(['name' => 'Zed Pending']);
        Rate::factory()->for($other, 'employee')->for($this->client)->create(['pay_frequency' => PayFrequency::SemiMonthly, 'effective_date' => '2026-01-01']);

        $this->actingAs($this->contractor)->post(route('employee.attendance.verification.fix', $this->period), [
            'attendance_id' => $day->id,
            'time_out' => '18:00',
            'reason' => 'Forgot to stop my timer.',
        ]);
        $this->post(route('employee.attendance.verification.submit', $this->period));

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.payroll', ['tab' => 'verification']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Payroll/Index')
                ->where('tab', 'verification')
                ->where('verification.period.id', $this->period->id)
                ->where('verification.period.fixWindowOpen', true)
                ->where('verification.counts.verified', 1)
                ->where('verification.counts.total', 2)
                ->missing('verification.counts.fixes')
                // View-only: only those who submitted, and not what they changed.
                ->has('verification.contractors', 1)
                ->where('verification.contractors.0.id', $this->contractor->id)
                ->missing('verification.contractors.0.fixes'));
    }

    public function test_time_history_groups_sessions_by_day_with_the_fixes(): void
    {
        $day = $this->attendance('2026-09-15');
        TimeLog::factory()->for($this->contractor, 'employee')->for($this->client)->count(2)->create(['date' => '2026-09-15', 'time_in' => '2026-09-15 09:00:00', 'time_out' => '2026-09-15 12:00:00']);
        TimeLog::factory()->for($this->contractor, 'employee')->for($this->client)->create(['date' => '2026-09-16', 'time_in' => '2026-09-16 09:00:00', 'time_out' => '2026-09-16 12:00:00']);

        $this->actingAs($this->contractor)->post(route('employee.attendance.verification.fix', $this->period), [
            'attendance_id' => $day->id,
            'time_out' => '18:00',
            'reason' => 'Forgot to stop my timer.',
        ]);

        $this->get(route('employee.time-history.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Employee/TimeHistory')
            ->where('days.meta.total', 2)
            ->where('days.data.0.date', '2026-09-16')
            ->has('days.data.0.fixes', 0)
            ->where('days.data.1.date', '2026-09-15')
            ->has('days.data.1.sessions', 2)
            ->where('days.data.1.fixes.0.after', '9:00 AM – 6:00 PM'));
    }

    public function test_submitting_as_verified_closes_the_fix(): void
    {
        $day = $this->attendance('2026-09-15');

        $this->actingAs($this->contractor)
            ->post(route('employee.attendance.verification.submit', $this->period))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(AttendanceVerification::sole()->verified_at);

        $this->post(route('employee.attendance.verification.fix', $this->period), [
            'attendance_id' => $day->id,
            'time_out' => '18:00',
            'reason' => 'Too late.',
        ])->assertSessionHasErrors('time_in');

        $this->post(route('employee.attendance.verification.submit', $this->period))->assertSessionHasErrors('time_in');
    }

    public function test_days_outside_the_period_and_periods_not_in_verification_are_refused(): void
    {
        $outside = $this->attendance('2026-09-26');

        $this->actingAs($this->contractor)
            ->post(route('employee.attendance.verification.fix', $this->period), [
                'attendance_id' => $outside->id,
                'time_out' => '18:00',
                'reason' => 'Not in this period.',
            ])
            ->assertSessionHasErrors('time_in');

        $this->period->update(['status' => PayrollPeriodStatus::Open]);

        $this->post(route('employee.attendance.verification.submit', $this->period))->assertSessionHasErrors('time_in');
        $this->get(route('employee.attendance.index'))->assertInertia(fn (Assert $page) => $page->where('verification', null));
    }

    public function test_contractors_not_paid_in_the_period_cannot_verify_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('employee.attendance.verification.submit', $this->period))
            ->assertSessionHasErrors('time_in');

        $this->assertSame(0, AttendanceVerification::count());
    }
}
