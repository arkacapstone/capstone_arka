<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;

/**
 * A contractor filed an overtime ticket; the Super Admin approves it with the amount.
 */
class OvertimeRequested extends ArkaNotification
{
    public function __construct(private readonly OvertimeRequest $ticket) {}

    protected function title(object $notifiable): string
    {
        return 'Overtime ticket to approve';
    }

    protected function message(object $notifiable): string
    {
        $this->ticket->loadMissing('employee:id,name', 'client:id,client_name');

        return "{$this->ticket->employee->name} filed {$this->ticket->duration()} h of overtime for {$this->ticket->client->client_name} on {$this->ticket->date->format('M j')}, approved by {$this->ticket->client_handler}.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('super-admin.requests', ['type' => 'overtime'], absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
