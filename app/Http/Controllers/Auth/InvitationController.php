<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Accounts\SendInvitation;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;

/**
 * The "Verify email & log in" button in the invite email. Opening it proves the address belongs to
 * the new contractor (or Admin), then sends them to the login page to use the default password
 * from the same email. On the first login they replace it and fill in their personal details.
 */
class InvitationController extends Controller
{
    public function __invoke(string $token, ActivityLogger $activity): RedirectResponse
    {
        $user = SendInvitation::findByToken($token);

        if (! $user || $user->status !== UserStatus::Active->value) {
            return to_route('login')->withErrors([
                'email' => __('This link is no longer valid. If you already verified your email, just log in. Otherwise, ask your administrator to send a new invite.'),
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'invitation_token' => null,
        ])->save();

        $activity->log('workforce', "Verified {$user->role->label()} email from invite", $user, "{$user->name} ({$user->employee_code})");

        return to_route('login')->with('status', __('Your email is verified. Log in with the username and default password from your email.'));
    }
}
