<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Every in-app notification: a title, a one-line message that says what happens next,
 * where it leads, and a category for its icon (Blueprint §16). Notifications are
 * informational only; they never replace the records or approvals themselves.
 */
abstract class ArkaNotification extends Notification
{
    use Queueable;

    abstract protected function title(object $notifiable): string;

    abstract protected function message(object $notifiable): string;

    /**
     * Relative URL the notification opens, if any.
     */
    protected function url(object $notifiable): ?string
    {
        return null;
    }

    /**
     * One of: account, schedule, attendance, leave, devotional, payslip, workforce.
     */
    protected function category(): string
    {
        return 'account';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, message: string, url: ?string, category: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title($notifiable),
            'message' => $this->message($notifiable),
            'url' => $this->url($notifiable),
            'category' => $this->category(),
        ];
    }
}
