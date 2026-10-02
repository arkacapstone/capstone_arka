<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\Dashboard\Contracts\DashboardWidget;

/**
 * Account and client coverage across the home office (Blueprint §3.1 Workforce Management).
 */
class WorkforceOverviewWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'workforce';
    }

    /**
     * @return array{activeTotal: int, employees: array{active: int, inactive: int}, admins: array{active: int, inactive: int}, activeClients: int}
     */
    public function data(): array
    {
        $counts = User::query()
            ->whereIn('role', [UserRole::Employee, UserRole::Admin])
            ->selectRaw('role, status, count(*) as total')
            ->groupBy('role', 'status')
            ->get();

        $employees = $this->split($counts, UserRole::Employee);
        $admins = $this->split($counts, UserRole::Admin);

        return [
            'activeTotal' => $employees['active'] + $admins['active'],
            'employees' => $employees,
            'admins' => $admins,
            'activeClients' => Client::query()->active()->count(),
        ];
    }

    /**
     * @param  iterable<User>  $counts
     * @return array{active: int, inactive: int}
     */
    private function split(iterable $counts, UserRole $role): array
    {
        $active = 0;
        $inactive = 0;

        foreach ($counts as $row) {
            if ($row->role !== $role) {
                continue;
            }

            if ($row->status === UserStatus::Active->value) {
                $active += (int) $row->total;
            } else {
                $inactive += (int) $row->total;
            }
        }

        return ['active' => $active, 'inactive' => $inactive];
    }
}
