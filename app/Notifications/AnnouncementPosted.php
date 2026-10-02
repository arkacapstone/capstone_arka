<?php

namespace App\Notifications;

/**
 * A Super Admin announcement (Blueprint §3.1 Notifications → Announcements).
 */
class AnnouncementPosted extends ArkaNotification
{
    public function __construct(private readonly string $heading, private readonly string $body) {}

    protected function title(object $notifiable): string
    {
        return "Announcement: {$this->heading}";
    }

    protected function message(object $notifiable): string
    {
        return $this->body;
    }

    protected function category(): string
    {
        return 'announcement';
    }
}
