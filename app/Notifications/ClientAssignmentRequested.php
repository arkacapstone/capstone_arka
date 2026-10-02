<?php

namespace App\Notifications;

use App\Models\ClientAssignmentRequest;

/**
 * An Admin gave a contractor a client; the Super Admin approves it and sets the rate.
 */
class ClientAssignmentRequested extends ArkaNotification
{
    public function __construct(private readonly ClientAssignmentRequest $request) {}

    protected function title(object $notifiable): string
    {
        return 'Client assignment to approve';
    }

    protected function message(object $notifiable): string
    {
        $this->request->loadMissing('employee:id,name', 'client:id,client_name', 'requester:id,name');

        return "{$this->request->requester->name} assigned {$this->request->employee->name} to {$this->request->clientName()}. Approve it and set the rate, or reject it.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('super-admin.requests', ['type' => 'clients'], absolute: false);
    }

    protected function category(): string
    {
        return 'workforce';
    }
}
