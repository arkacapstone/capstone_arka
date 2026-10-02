<?php

namespace App\Notifications;

use App\Models\Schedule;
use Carbon\CarbonImmutable;

/**
 * Tells the contractor about a new or changed schedule (Blueprint §6).
 */
class ScheduleChanged extends ArkaNotification
{
    public function __construct(private readonly Schedule $schedule, private readonly bool $created) {}

    protected function title(object $notifiable): string
    {
        return $this->created ? 'New schedule assigned' : 'Your schedule changed';
    }

    protected function message(object $notifiable): string
    {
        $this->schedule->loadMissing('client:id,client_name');

        $time = fn (string $value) => CarbonImmutable::parse($value)->format('g:i A');
        $window = $time($this->schedule->start_time).' – '.$time($this->schedule->end_time);

        return "{$this->schedule->client->client_name}: {$window}, starting {$this->schedule->start_date->format('M j, Y')}.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.time-tracker.index', absolute: false);
    }

    protected function category(): string
    {
        return 'schedule';
    }
}
