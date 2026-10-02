<?php

namespace App\Actions\Accounts;

use App\Models\User;

/**
 * An invited account with its one-time verify link, its default password and whether the email went out.
 */
final readonly class InvitedAccount
{
    public function __construct(
        public User $user,
        public string $invitationUrl,
        public string $defaultPassword,
        public bool $emailed,
    ) {}
}
