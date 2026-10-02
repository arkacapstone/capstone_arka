<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * A schedule was saved on top of another one for the same employee (Admin flow §IX).
 * Overlaps can be intentional (two part-time clients), so this only asks for a look.
 */
class ScheduleOverlapDetected extends ArkaNotification
{
    public function __construct(private readonly Schedule $schedule, private readonly int $overlaps) {}

    protected function title(object $notifiable): string
    {
        return 'Schedule overlap saved';
    }

    protected function message(object $notifiable): string
    {
        $this->schedule->loadMissing('employee:id,name', 'client:id,client_name');
        $count = $this->overlaps === 1 ? 'another schedule' : "{$this->overlaps} other schedules";

        return "{$this->schedule->employee->name}'s {$this->schedule->client->client_name} schedule overlaps {$count}. Check that it's intentional.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.scheduling.index', absolute: false);
    }

    protected function category(): string
    {
        return 'schedule';
    }
}
