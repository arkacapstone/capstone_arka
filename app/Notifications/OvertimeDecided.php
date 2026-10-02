<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;

/**
 * Tells the contractor whether their overtime ticket was approved and when it is paid.
 */
class OvertimeDecided extends ArkaNotification
{
    public function __construct(private readonly OvertimeRequest $ticket) {}

    protected function title(object $notifiable): string
    {
        return $this->ticket->status === OvertimeRequest::STATUS_APPROVED ? 'Overtime approved' : 'Overtime not approved';
    }

    protected function message(object $notifiable): string
    {
        $this->ticket->loadMissing('client:id,client_name');
        $what = "{$this->ticket->duration()} h for {$this->ticket->client->client_name} on {$this->ticket->date->format('M j')}";

        if ($this->ticket->status === OvertimeRequest::STATUS_APPROVED) {
            return "{$what}: ₱".number_format((float) $this->ticket->amount, 2).' will be added to your next payslip.';
        }

        return $this->ticket->review_note ? "{$what}. Reason: {$this->ticket->review_note}" : "{$what}.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.overtime.index', absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
