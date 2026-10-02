<?php

namespace App\Notifications;

use App\Models\Rate;

/**
 * The Super Admin assigned the contractor to a client (Blueprint §5). Rates are never shown here.
 */
class ClientAssigned extends ArkaNotification
{
    public function __construct(private readonly Rate $rate) {}

    protected function title(object $notifiable): string
    {
        return 'New client assignment';
    }

    protected function message(object $notifiable): string
    {
        $this->rate->loadMissing('client:id,client_name');

        return "You're assigned to {$this->rate->client->client_name} from {$this->rate->effective_date->format('M j, Y')}. Your schedule for this client comes next.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.time-tracker.index', absolute: false);
    }

    protected function category(): string
    {
        return 'workforce';
    }
}
