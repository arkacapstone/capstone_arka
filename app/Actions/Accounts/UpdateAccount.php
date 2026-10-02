<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Services\ActivityLogger;

class UpdateAccount
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array{name: string, email: string, phone_number?: ?string, birthday?: ?string}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        $user->fill($attributes);

        $changed = array_keys($user->getDirty());

        if ($changed === []) {
            return $user;
        }

        $user->save();

        $this->activity->log(
            'workforce',
            "Updated {$user->role->label()} account",
            $user,
            "{$user->name}: ".implode(', ', str_replace('_', ' ', $changed)),
        );

        return $user;
    }
}
