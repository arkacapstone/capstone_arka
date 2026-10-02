<?php

namespace App\Actions\Accounts;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Activates or deactivates an account. Accounts are never deleted so their
 * attendance, payroll and approval history stays intact.
 */
class ChangeAccountStatus
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(User $user, UserStatus $status): User
    {
        if ($user->status === $status->value) {
            return $user;
        }

        $user->forceFill(['status' => $status->value])->save();

        if ($status === UserStatus::Inactive) {
            // Sign the account out everywhere it is currently logged in.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $verb = $status === UserStatus::Active ? 'Activated' : 'Deactivated';
        $this->activity->log('workforce', "{$verb} {$user->role->label()} account", $user, $user->name);

        return $user;
    }
}
