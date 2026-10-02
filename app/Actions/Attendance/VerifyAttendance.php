<?php

namespace App\Actions\Attendance;

use App\Enums\CorrectionStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceVerification;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Notifications\AttendanceVerified;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\Payroll\PayrollCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll attendance verification by the contractor. While a period is in Verification, the
 * contractor checks their attendance and may fix the time in/out of any day, as often as needed,
 * but only on the day the Super Admin opened verification (until midnight). Each fix is saved right
 * away with the time it replaced, and the contractor then submits their attendance as verified once.
 */
class VerifyAttendance
{
    public function __construct(
        private readonly ApplyAttendanceTimes $applyTimes,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    /**
     * The period in Verification that this contractor is paid in, if any.
     */
    public static function openPeriodFor(User $contractor): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('status', PayrollPeriodStatus::Verification)
            ->orderBy('start_date')
            ->get()
            ->first(fn (PayrollPeriod $period) => PayrollCalculator::ratesFor($period)->where('employee_id', $contractor->id)->exists());
    }

    public function fix(User $contractor, PayrollPeriod $period, Attendance $attendance, ?string $timeIn, ?string $timeOut, string $reason): AttendanceCorrection
    {
        $verification = $this->openVerification($contractor, $period);

        if (! $period->fixWindowOpen()) {
            throw ValidationException::withMessages(['time_in' => 'Fixing closed at midnight on '.$period->verification_opened_at?->format('M j, Y').'. Ask an Admin to correct anything else.']);
        }

        if ($attendance->employee_id !== $contractor->id || ! $attendance->date->between($period->start_date, $period->end_date)) {
            throw ValidationException::withMessages(['time_in' => 'This day is not part of the period being verified.']);
        }

        $date = CarbonImmutable::parse($attendance->date->toDateString());

        $correction = DB::transaction(function () use ($contractor, $verification, $attendance, $date, $timeIn, $timeOut, $reason) {
            $original = [
                'original_time_in' => $attendance->time_in?->format('H:i'),
                'original_time_out' => $attendance->time_out?->format('H:i'),
            ];

            $this->applyTimes->handle($contractor, $date, $attendance, $timeIn, $timeOut);

            return AttendanceCorrection::create([
                'attendance_id' => $attendance->id,
                'employee_id' => $contractor->id,
                'verification_id' => $verification->id,
                'date' => $date,
                'source' => 'employee',
                'field_corrected' => FieldCorrected::from($timeIn, $timeOut),
                ...$original,
                'requested_time_in' => $timeIn,
                'requested_time_out' => $timeOut,
                'reason' => $reason,
                'status' => CorrectionStatus::Approved,
                'reviewed_by' => $contractor->id,
                'reviewed_at' => now(),
                'admin_remarks' => 'Fixed by the contractor during payroll verification.',
            ]);
        });

        $this->activity->log('attendance', 'Fixed attendance during payroll verification', $correction, "{$contractor->name} · {$date->format('M j, Y')}");

        return $correction;
    }

    public function submit(User $contractor, PayrollPeriod $period): AttendanceVerification
    {
        $verification = $this->openVerification($contractor, $period);
        $verification->update(['verified_at' => now()]);

        $this->activity->log('attendance', 'Verified attendance for payroll', $verification, "{$contractor->name} · {$period->period_name}");

        // The Admin reviews the changes; the Super Admin only hears once the Admin submits the verified period.
        // An Admin verifying their own attendance doesn't need to be told about it.
        $this->notifier->admins(new AttendanceVerified($verification), except: $contractor);

        return $verification;
    }

    /**
     * The contractor's verification for the period, as long as they can still change it.
     */
    private function openVerification(User $contractor, PayrollPeriod $period): AttendanceVerification
    {
        if ($period->status !== PayrollPeriodStatus::Verification || ! PayrollCalculator::ratesFor($period)->where('employee_id', $contractor->id)->exists()) {
            throw ValidationException::withMessages(['time_in' => 'Attendance verification is not open for this period.']);
        }

        if ($period->isSubmittedByAdmin()) {
            throw ValidationException::withMessages(['time_in' => 'An Admin already submitted this period to the Super Admin, so it can no longer be changed.']);
        }

        $verification = AttendanceVerification::query()->firstOrCreate(['period_id' => $period->id, 'employee_id' => $contractor->id]);

        if ($verification->verified_at !== null) {
            throw ValidationException::withMessages(['time_in' => 'You already submitted your attendance as verified.']);
        }

        return $verification;
    }
}
