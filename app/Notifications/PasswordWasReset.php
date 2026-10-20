<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a new default password after an Admin (or the Super Admin) resets a forgotten one, with a
 * button to the login page. Sent immediately (not queued) so the plain password never sits in the jobs table.
 */
class PasswordWasReset extends Notification
{
    public function __construct(
        private readonly string $loginUrl,
        private readonly string $defaultPassword,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ARKA password was reset')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your ARKA password was reset. Use these details to log in:')
            ->line("**Username:** {$notifiable->email}")
            ->line("**Default password:** {$this->defaultPassword}")
            ->action('Log in to ARKA', $this->loginUrl)
            ->line('After you log in, you will create your own password. Your old password no longer works.')
            ->line('If you did not ask for a password reset, contact your administrator right away.');
    }
}
