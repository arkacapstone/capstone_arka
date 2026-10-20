<?php

namespace App\Actions\Workforce;

use App\Models\User;

/**
 * A contractor's Full-Time / Part-Time classification follows their clients: any Full-Time client
 * makes them a Full-Time contractor; otherwise Part-Time clients make them Part-Time. With no
 * classified client the saved value is kept.
 */
class SyncEmploymentType
{
    public function handle(User $contractor): User
    {
        // Nothing to save: the classification is read from the open client assignments
        // (User::employmentType()), so it is always up to date.
        return $contractor;
    }
}
