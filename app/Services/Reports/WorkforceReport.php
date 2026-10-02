<?php

namespace App\Services\Reports;

use App\Models\Rate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admins and contractors with their classification, status and client assignments. A snapshot of
 * today; the From / To dates limit it to accounts created on or before the end date.
 */
class WorkforceReport implements Report
{
    public function key(): string
    {
        return 'workforce';
    }

    public function title(): string
    {
        return 'Workforce Report';
    }

    public function summaryColumns(): array
    {
        return ['Group', 'Active', 'Inactive', 'Total'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        $people = $this->query($filters)->get();

        return $people
            ->groupBy(fn (User $user) => $user->role->label().($user->employment_type ? " · {$user->employment_type->label()}" : ''))
            ->map(fn ($group, string $label) => [
                $label,
                $group->filter(fn (User $user) => $user->status->value === 'active')->count(),
                $group->filter(fn (User $user) => $user->status->value !== 'active')->count(),
                $group->count(),
            ])
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Code', 'Role', 'Type', 'Status', 'Clients', 'Joined'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with('currentRates.client:id,client_name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->employee_code,
                $user->role->label(),
                $user->employment_type?->label(),
                ucfirst($user->status->value),
                $user->currentRates->map(fn (Rate $rate) => $rate->client?->client_name)->filter()->unique()->implode(', ') ?: null,
                $user->created_at->toDateString(),
            ])
            ->all();
    }

    /**
     * @return Builder<User>
     */
    private function query(ReportFilters $filters): Builder
    {
        return User::query()
            ->workforce()
            ->whereDate('created_at', '<=', $filters->to)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
