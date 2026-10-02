<?php

namespace App\Actions\Attendance;

use App\Enums\CorrectionStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Notifications\AttendanceCorrected;
use App\Services\ActivityLogger;
use App\Services\Attendance\AttendanceLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Admin-initiated "Fix this" correction (Admin flow §V). The original value is
 * kept on the correction record, never silently overwritten.
 */
class CorrectAttendance
{
    public function __construct(
        private readonly ApplyAttendanceTimes $applyTimes,
        private readonly AttendanceLock $lock,
        private readonly ActivityLogger $activity,
    ) {}

    public function handle(User $admin, User $employee, CarbonImmutable $date, ?Attendance $attendance, ?string $timeIn, ?string $timeOut, string $reason): AttendanceCorrection
    {
        $this->lock->ensureOpen($date, 'time_in');

        $correction = DB::transaction(function () use ($admin, $employee, $date, $attendance, $timeIn, $timeOut, $reason) {
            $original = [
                'original_time_in' => $attendance?->time_in?->format('H:i'),
                'original_time_out' => $attendance?->time_out?->format('H:i'),
            ];

            $attendance = $this->applyTimes->handle($employee, $date, $attendance, $timeIn, $timeOut);

            return AttendanceCorrection::create([
                'attendance_id' => $attendance->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'source' => 'admin',
                'field_corrected' => FieldCorrected::from($timeIn, $timeOut),
                ...$original,
                'requested_time_in' => $timeIn,
                'requested_time_out' => $timeOut,
                'reason' => $reason,
                'status' => CorrectionStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        });

        $this->activity->log('attendance', 'Corrected attendance', $correction, "{$employee->name} · {$date->format('M j, Y')}");
        $employee->notify(new AttendanceCorrected($correction));

        return $correction;
    }
}
