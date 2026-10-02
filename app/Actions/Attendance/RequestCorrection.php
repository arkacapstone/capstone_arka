<?php

namespace App\Actions\Attendance;

use App\Enums\CorrectionStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Notifications\CorrectionRequested;
use App\Services\ActivityLogger;
use App\Services\Attendance\AttendanceLock;
use App\Services\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * A contractor's "Fix this" request (Contractor flow §VI). It stays Pending until an Admin
 * approves or closes it; the official attendance only changes on approval.
 */
class RequestCorrection
{
    public function __construct(
        private readonly AttendanceLock $lock,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    public function handle(User $employee, CarbonImmutable $date, ?Attendance $attendance, ?string $timeIn, ?string $timeOut, string $reason, ?UploadedFile $proof = null): AttendanceCorrection
    {
        $this->lock->ensureOpen($date);

        $alreadyPending = $employee->correctionRequests()
            ->pending()
            ->whereDate('date', $date)
            ->when($attendance, fn ($query) => $query->where('attendance_id', $attendance->id))
            ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages(['date' => 'You already have a pending request for this day. An Admin will review it soon.']);
        }

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance?->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'source' => 'employee',
            'field_corrected' => FieldCorrected::from($timeIn, $timeOut),
            'original_time_in' => $attendance?->time_in?->format('H:i'),
            'original_time_out' => $attendance?->time_out?->format('H:i'),
            'requested_time_in' => $timeIn,
            'requested_time_out' => $timeOut,
            'reason' => $reason,
            'proof_path' => $proof?->store("corrections/{$employee->id}", 'local'),
            'status' => CorrectionStatus::Pending,
        ]);

        $this->activity->log('attendance', 'Requested attendance correction', $correction, "{$employee->name} · {$date->format('M j, Y')}");
        $this->notifier->admins(new CorrectionRequested($correction), except: $employee);

        return $correction;
    }
}
