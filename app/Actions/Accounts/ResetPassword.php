<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Notifications\PasswordWasReset;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * For a verified account that forgot its password: issues a new default password, emails it with a
 * button to the login page, and signs the account out everywhere. On the next login they must create
 * their own password. The email stays verified.
 */
class ResetPassword
{
    public function __construct(
        private readonly DefaultPassword $passwords,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Reset by an Admin or the Super Admin. A mail failure keeps the new password so they can share it privately.
     *
     * @return array{url: string, defaultPassword: string, emailed: bool}
     */
    public function handle(User $user): array
    {
        $defaultPassword = $this->reset($user);
        $url = route('login');

        try {
            $user->notify(new PasswordWasReset($url, $defaultPassword));
        } catch (Throwable $exception) {
            report($exception);

            return ['url' => $url, 'defaultPassword' => $defaultPassword, 'emailed' => false];
        }

        return ['url' => $url, 'defaultPassword' => $defaultPassword, 'emailed' => true];
    }

    /**
     * Reset by the account owner from "Forgot your password?". Nobody else sees the new password, so
     * if the email cannot be sent nothing changes and the old password keeps working.
     */
    public function handleOrUndo(User $user): bool
    {
        try {
            DB::transaction(function () use ($user) {
                $user->notify(new PasswordWasReset(route('login'), $this->reset($user)));
            });
        } catch (Throwable $exception) {
            report($exception);
            $user->refresh();

            return false;
        }

        return true;
    }

    private function reset(User $user): string
    {
        $defaultPassword = $this->passwords->generate();

        $user->forceFill([
            'password' => $defaultPassword,
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();

        // The old password no longer works anywhere, including "remember me" sessions.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->activity->log('workforce', "Reset {$user->role->label()} password", $user, $user->name);

        return $defaultPassword;
    }
}
