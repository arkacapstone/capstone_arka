<?php

namespace App\Notifications;

use App\Models\TimeLog;

/**
 * A timer was still running when the shift ended, so it stopped at the scheduled end time.
 */
class TimerStoppedAtShiftEnd extends ArkaNotification
{
    public function __construct(private readonly TimeLog $log) {}

    protected function title(object $notifiable): string
    {
        return 'Timer stopped at the end of your shift';
    }

    protected function message(object $notifiable): string
    {
        $this->log->loadMissing('client:id,client_name');

        return "Your {$this->log->client->client_name} timer stopped at {$this->log->time_out->format('g:i A')}, when your shift ended. Worked overtime approved by your client handler? File an overtime ticket.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.overtime.index', absolute: false);
    }

    protected function category(): string
    {
        return 'attendance';
    }
}
