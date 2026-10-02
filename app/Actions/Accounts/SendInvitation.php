<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails the login details (username and a default password) with a one-time link that verifies
 * the email address. Sending a new invite issues a new default password and makes any earlier
 * link stop working. A mail failure never undoes the account: it is reported and the Admin can resend later.
 */
class SendInvitation
{
    public function __construct(private readonly DefaultPassword $passwords) {}

    /**
     * @return array{url: string, defaultPassword: string, emailed: bool}
     */
    public function handle(User $user): array
    {
        $token = Str::random(48);
        $defaultPassword = $this->passwords->generate();

        $user->forceFill([
            'password' => $defaultPassword,
            'must_change_password' => true,
            'email_verified_at' => null,
            'invitation_token' => hash('sha256', $token),
            'invitation_sent_at' => now(),
        ])->save();

        $url = route('invitation.verify', $token);

        try {
            $user->notify(new AccountInvitation($url, $defaultPassword));
        } catch (Throwable $exception) {
            report($exception);

            return ['url' => $url, 'defaultPassword' => $defaultPassword, 'emailed' => false];
        }

        return ['url' => $url, 'defaultPassword' => $defaultPassword, 'emailed' => true];
    }

    /**
     * The invited account a link belongs to, if the link is still valid.
     */
    public static function findByToken(string $token): ?User
    {
        $user = User::query()->where('invitation_token', hash('sha256', $token))->first();

        return $user && ! $user->invitationExpired() ? $user : null;
    }
}
