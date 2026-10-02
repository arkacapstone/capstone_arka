<?php

namespace App\Services\Dashboard\Widgets\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

class EmployeeOverviewWidget implements DashboardWidget
{
    public function __construct(private readonly CarbonImmutable $today) {}

    public function key(): string
    {
        return 'employees';
    }

    /**
     * @return array{active: int, inactive: int, newThisWeek: int, awaitingFirstLogin: int}
     */
    public function data(): array
    {
        $employees = User::query()->withRole(UserRole::Employee);

        return [
            'active' => (clone $employees)->where('status', UserStatus::Active->value)->count(),
            'inactive' => (clone $employees)->where('status', UserStatus::Inactive->value)->count(),
            'newThisWeek' => (clone $employees)->where('created_at', '>=', $this->today->startOfWeek())->count(),
            'awaitingFirstLogin' => (clone $employees)->where('must_change_password', true)->count(),
        ];
    }
}
