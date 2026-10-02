<?php

namespace App\Actions\Attendance;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Notifications\AttendanceCorrected;
use App\Services\ActivityLogger;
use App\Services\Attendance\AttendanceLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Admin's decision on a employee-submitted correction request (Blueprint §8, §15).
 * An approved request updates the official attendance before the cutoff.
 */
class ReviewCorrectionRequest
{
    public function __construct(
        private readonly ApplyAttendanceTimes $applyTimes,
        private readonly AttendanceLock $lock,
        private readonly ActivityLogger $activity,
    ) {}

    public function approve(User $admin, AttendanceCorrection $request, ?string $remarks = null): AttendanceCorrection
    {
        $this->ensurePending($request);
        $date = CarbonImmutable::parse($request->date->toDateString());
        $this->lock->ensureOpen($date, 'status');

        DB::transaction(function () use ($admin, $request, $remarks, $date) {
            $attendance = $this->applyTimes->handle(
                $request->employee,
                $date,
                $request->attendance,
                $request->requested_time_in ? substr($request->requested_time_in, 0, 5) : null,
                $request->requested_time_out ? substr($request->requested_time_out, 0, 5) : null,
            );

            $request->update([
                'attendance_id' => $attendance->id,
                'status' => CorrectionStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_remarks' => $remarks,
            ]);
        });

        return $this->finish($request, 'Approved correction request');
    }

    public function reject(User $admin, AttendanceCorrection $request, string $remarks): AttendanceCorrection
    {
        $this->ensurePending($request);

        $request->update([
            'status' => CorrectionStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_remarks' => $remarks,
        ]);

        return $this->finish($request, 'Closed correction request');
    }

    private function ensurePending(AttendanceCorrection $request): void
    {
        if ($request->status !== CorrectionStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
        }
    }

    private function finish(AttendanceCorrection $request, string $action): AttendanceCorrection
    {
        $this->activity->log('attendance', $action, $request, "{$request->employee->name} · {$request->date->format('M j, Y')}");
        $request->employee->notify(new AttendanceCorrected($request));

        return $request;
    }
}
