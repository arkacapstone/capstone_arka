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
     * Per portal: modules kept at the top of the sidebar and at the bottom, in this order. The rest
     * are alphabetical in between.
     */
    private const FIRST = [
        ViewMode::SUPER_ADMIN => ['dashboard', 'workforce'],
        ViewMode::ADMIN => ['dashboard'],
        // The contractor's Time Tracker is used every day.
        ViewMode::EMPLOYEE => ['dashboard', 'time-tracker'],
    ];

    private const LAST = [
        ViewMode::SUPER_ADMIN => ['notifications'],
    ];

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

        // Sort key: [group (0 top, 1 middle, 2 bottom), position in the pinned list or label].
        $first = self::FIRST[$viewMode] ?? [];
        $last = self::LAST[$viewMode] ?? [];
        $sortKey = fn (array $module) => match (true) {
            in_array($module['key'], $first, true) => [0, array_search($module['key'], $first, true)],
            in_array($module['key'], $last, true) => [2, array_search($module['key'], $last, true)],
            default => [1, $module['label']],
        };
        usort($modules, fn (array $a, array $b) => $sortKey($a) <=> $sortKey($b));

        return [
            ...$modules,
            ['key' => 'profile', 'label' => 'Profile', 'href' => route('profile.edit'), 'routeName' => 'profile.edit', 'match' => 'profile.*'],
        ];
    }
}
