<?php

namespace App\Notifications;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;

/**
 * A new leave request is waiting for the Super Admin (Blueprint §10).
 */
class LeaveRequestSubmitted extends ArkaNotification
{
    public function __construct(private readonly LeaveRequest $leave) {}

    protected function title(object $notifiable): string
    {
        return $this->leave->status === LeaveRequestStatus::NeedsVerification ? 'Leave request needs verification' : 'New leave request';
    }

    protected function message(object $notifiable): string
    {
        $this->leave->loadMissing('employee:id,name');

        $message = "{$this->leave->employee->name} requested leave for {$this->leave->start_date->format('M j')} – {$this->leave->end_date->format('M j, Y')}.";

        return $this->leave->status === LeaveRequestStatus::NeedsVerification
            ? "{$message} Client marked as informed, but no proof was attached."
            : $message;
    }

    protected function url(object $notifiable): ?string
    {
        return route('super-admin.requests', absolute: false);
    }

    protected function category(): string
    {
        return 'leave';
    }
}
