<?php

namespace App\Actions\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveRequestDecided;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Http\UploadedFile;

/**
 * Files a leave request for the Super Admin to decide (Blueprint §10, Contractor flow §VII).
 * Marking the client as informed without proof flags it Needs Verification — nothing is rejected.
 */
class SubmitLeaveRequest
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array{paid: bool, start_date: string, end_date: string, reason: string, client_informed: bool, notes?: ?string}  $details
     */
    public function handle(User $employee, array $details, ?UploadedFile $proof = null): LeaveRequest
    {
        $needsVerification = $details['client_informed'] && $proof === null;

        $leave = $employee->leaveRequests()->create([
            'leave_type_id' => $this->leaveType($details['paid'])->id,
            'start_date' => $details['start_date'],
            'end_date' => $details['end_date'],
            'reason' => $details['reason'],
            'client_informed' => $details['client_informed'],
            'proof_path' => $proof?->store("leave/{$employee->id}", 'local'),
            'notes' => $details['notes'] ?? null,
            'status' => $needsVerification ? LeaveRequestStatus::NeedsVerification : LeaveRequestStatus::PendingApproval,
        ]);

        $this->activity->log('requests', 'Submitted leave request', $leave, "{$employee->name} · {$leave->start_date->format('M j')} – {$leave->end_date->format('M j, Y')}");
        $this->notifier->superAdmins(new LeaveRequestSubmitted($leave));

        if ($needsVerification) {
            $employee->notify(new LeaveRequestDecided($leave));
        }

        return $leave;
    }

    public function cancel(LeaveRequest $leave): LeaveRequest
    {
        $leave->update(['status' => LeaveRequestStatus::Cancelled]);
        $this->activity->log('requests', 'Cancelled leave request', $leave);

        return $leave;
    }

    /**
     * Paid or unpaid leave (the segmented toggle). Uses the Super Admin's leave types when
     * they exist, and creates a default one otherwise.
     */
    private function leaveType(bool $paid): LeaveType
    {
        return LeaveType::query()->where('is_paid', $paid)->orderBy('id')->first()
            ?? LeaveType::create(['leave_type_name' => $paid ? 'Paid leave' : 'Unpaid leave', 'is_paid' => $paid]);
    }
}
