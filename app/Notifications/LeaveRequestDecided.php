<?php

namespace App\Notifications;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;

/**
 * Tells the contractor their leave request was decided or flagged (Contractor flow §X).
 */
class LeaveRequestDecided extends ArkaNotification
{
    public function __construct(private readonly LeaveRequest $leave) {}

    protected function title(object $notifiable): string
    {
        return match ($this->leave->status) {
            LeaveRequestStatus::Approved => 'Leave approved',
            LeaveRequestStatus::NeedsVerification => 'Leave request flagged for verification',
            default => 'Leave request closed',
        };
    }

    protected function message(object $notifiable): string
    {
        $dates = $this->leave->start_date->format('M j').' – '.$this->leave->end_date->format('M j, Y');

        return match ($this->leave->status) {
            LeaveRequestStatus::Approved => "Your leave for {$dates} is approved and shows on your attendance.",
            LeaveRequestStatus::NeedsVerification => "Your leave for {$dates} is with the Super Admin for verification. Nothing is rejected, and you can attach proof by submitting it again.",
            default => "Your leave request for {$dates} was closed without approval. Your attendance for those days is unchanged.",
        };
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.leave.index', absolute: false);
    }

    protected function category(): string
    {
        return 'leave';
    }
}
