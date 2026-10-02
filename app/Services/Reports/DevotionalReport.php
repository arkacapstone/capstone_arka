<?php

namespace App\Services\Reports;

use App\Models\Devotional;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Devotional compliance for the period. For monitoring only; it never touches payroll (Blueprint §9).
 */
class DevotionalReport implements Report
{
    public function key(): string
    {
        return 'devotional';
    }

    public function title(): string
    {
        return 'Devotional Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Submitted', 'Late', 'Not submitted'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        $days = $this->days($filters);
        $submissions = $this->submissions($filters)->groupBy('employee_id');

        return $this->employees($filters)
            ->map(function (User $employee) use ($submissions, $days) {
                $own = $submissions->get($employee->id, collect());

                return [
                    $employee->name,
                    $own->count(),
                    $own->filter(fn (Devotional $devotional) => $devotional->isLate())->count(),
                    max(0, $days - $own->count()),
                ];
            })
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Date', 'Contractor', 'Title', 'Status'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        $submissions = $this->submissions($filters)->groupBy(fn (Devotional $devotional) => $devotional->employee_id.'|'.$devotional->date->toDateString());
        $employees = $this->employees($filters);
        $rows = [];

        foreach (CarbonPeriod::create($filters->from, $filters->to) as $date) {
            foreach ($employees as $employee) {
                /** @var Devotional|null $devotional */
                $devotional = $submissions->get($employee->id.'|'.$date->toDateString())?->first();
                $status = match (true) {
                    $devotional === null => 'Not submitted',
                    $devotional->isLate() => 'Submitted (late)',
                    default => 'Submitted',
                };

                if ($filters->status === null || str_starts_with(strtolower($status), $filters->status === 'submitted' ? 'submitted' : 'not')) {
                    $rows[] = [$date->toDateString(), $employee->name, $devotional?->title, $status];
                }
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, User>
     */
    private function employees(ReportFilters $filters): Collection
    {
        return User::query()
            ->workforce()
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->whereKey($id), fn (Builder $query) => $query->active())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Devotional>
     */
    private function submissions(ReportFilters $filters): Collection
    {
        return Devotional::query()
            ->whereDate('date', '>=', $filters->from)
            ->whereDate('date', '<=', $filters->to)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id))
            ->get();
    }

    private function days(ReportFilters $filters): int
    {
        return (int) $filters->from->diffInDays($filters->to) + 1;
    }
}
