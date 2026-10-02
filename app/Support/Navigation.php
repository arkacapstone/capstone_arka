<?php

namespace App\Support;

use App\Enums\AdminModule;
use App\Enums\EmployeeModule;
use App\Enums\SuperAdminModule;
use App\Models\User;

/**
 * Builds the sidebar for the signed-in user's current view. One shell, role-specific modules.
 */
class Navigation
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function for(?User $user, ?string $viewMode): array
    {
        if ($user === null) {
            return [];
        }

        $modules = match ($viewMode) {
            ViewMode::SUPER_ADMIN => SuperAdminModule::navigation(),
            ViewMode::ADMIN => AdminModule::navigation(),
            ViewMode::EMPLOYEE => EmployeeModule::navigation(),
            default => [],
        };

        return [
            ...$modules,
            ['key' => 'profile', 'label' => 'Profile', 'href' => route('profile.edit'), 'routeName' => 'profile.edit', 'match' => 'profile.*'],
        ];
    }
}
