<?php

namespace App\Actions\Workforce;

use App\Enums\EmploymentType;
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
        $types = $contractor->currentRates()->whereNotNull('employment_type')->pluck('employment_type');

        $type = match (true) {
            $types->contains(EmploymentType::FullTime) => EmploymentType::FullTime,
            $types->contains(EmploymentType::PartTime) => EmploymentType::PartTime,
            default => $contractor->employment_type,
        };

        if ($type !== $contractor->employment_type) {
            $contractor->update(['employment_type' => $type]);
        }

        return $contractor;
    }
}
