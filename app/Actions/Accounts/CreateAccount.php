<?php

namespace App\Actions\Accounts;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\EmployeeAwaitingFirstLogin;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an account with a company code and emails the owner their username, a default password and a
 * verify link (Blueprint §18, step 2). They can log in once the email is verified, then replace the
 * password and fill in their personal details.
 */
class CreateAccount
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
        private readonly SendInvitation $sendInvitation,
    ) {}

    /**
     * @param  array{name: string, email: string, phone_number?: ?string, birthday?: ?string}  $attributes
     */
    public function handle(UserRole $role, array $attributes): InvitedAccount
    {
        $user = DB::transaction(function () use ($role, $attributes) {
            $user = User::create([
                ...$attributes,
                'employee_code' => User::nextEmployeeCode(),
                'role' => $role,
                'status' => UserStatus::Active->value,
                // Replaced by the default password SendInvitation emails.
                'password' => Str::password(40),
                'must_change_password' => false,
            ]);

            $this->activity->log('workforce', "Invited {$role->label()}", $user, "{$user->name} ({$user->employee_code})");

            return $user;
        });

        $invitation = $this->sendInvitation->handle($user);

        if ($role === UserRole::Employee) {
            $this->notifier->admins(new EmployeeAwaitingFirstLogin($user), except: Auth::user());
        }

        return new InvitedAccount($user, $invitation['url'], $invitation['defaultPassword'], $invitation['emailed']);
    }
}
