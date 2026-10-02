<?php

namespace App\Notifications;

use App\Models\ClientAssignmentRequest;

/**
 * Tells the Admin who asked whether the Super Admin approved the client assignment.
 */
class ClientAssignmentDecided extends ArkaNotification
{
    public function __construct(private readonly ClientAssignmentRequest $request) {}

    protected function title(object $notifiable): string
    {
        return $this->request->status === ClientAssignmentRequest::STATUS_APPROVED ? 'Client assignment approved' : 'Client assignment rejected';
    }

    protected function message(object $notifiable): string
    {
        $this->request->loadMissing('employee:id,name', 'client:id,client_name');
        $who = "{$this->request->employee->name} → {$this->request->clientName()}";

        if ($this->request->status === ClientAssignmentRequest::STATUS_APPROVED) {
            return "{$who}. You can now schedule them for this client.";
        }

        return $this->request->review_note ? "{$who}. Reason: {$this->request->review_note}" : "{$who}. Nothing was changed.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.scheduling.clients.index', absolute: false);
    }

    protected function category(): string
    {
        return 'schedule';
    }
}
