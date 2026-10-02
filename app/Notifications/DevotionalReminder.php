<?php

namespace App\Notifications;

/**
 * A quiet evening reminder for today's devotional (Contractor flow §X). Never urgent, never a penalty.
 */
class DevotionalReminder extends ArkaNotification
{
    protected function title(object $notifiable): string
    {
        return "Today's devotional";
    }

    protected function message(object $notifiable): string
    {
        return "A quiet reminder: today's devotional hasn't been uploaded yet. It counts as on time until midnight.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.devotionals.index', absolute: false);
    }

    protected function category(): string
    {
        return 'devotional';
    }
}
