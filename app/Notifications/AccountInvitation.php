<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a newly hired contractor (or a new Admin) their username and default password, with a button
 * that verifies the email address and opens the login page. Sent immediately (not queued) so the
 * plain password never sits in the jobs table.
 */
class AccountInvitation extends Notification
{
    public function __construct(
        private readonly string $url,
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
        $role = $notifiable->role->label();
        $lowerRole = strtolower($role);

        return (new MailMessage)
            ->subject('Your ARKA account is ready')
            ->greeting("Hi {$notifiable->name},")
            ->line("You have been added to ARKA as {$this->article($lowerRole)} {$lowerRole}. Use these details to log in:")
            ->line("**Username:** {$notifiable->email}")
            ->line("**Default password:** {$this->defaultPassword}")
            ->line("**{$role} ID:** {$notifiable->employee_code}")
            ->line('First, click the button below to verify that this is your email. You can log in only after your email is verified.')
            ->action('Verify email & log in', $this->url)
            ->line('On your first login you will create your own password and fill in your personal details.')
            ->line('This button works once and expires in '.User::INVITATION_VALID_DAYS.' days. If it expires, ask your administrator to send a new invite.')
            ->line('If you were not expecting this email, you can ignore it.');
    }

    private function article(string $word): string
    {
        return in_array($word[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
    }
}
