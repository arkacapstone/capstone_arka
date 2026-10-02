<?php

namespace App\Services\Reports;

use App\Enums\Weekday;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Work schedules that apply at some point between From and To.
 */
class ScheduleReport implements Report
{
    public function key(): string
    {
        return 'schedule';
    }

    public function title(): string
    {
        return 'Schedule Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Schedules', 'Clients', 'Expected hours / week'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with('employee:id,name')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($schedules) => [
                $schedules->first()->employee->name,
                $schedules->count(),
                $schedules->pluck('client_id')->filter()->unique()->count(),
                round($schedules->where('status', Schedule::STATUS_ACTIVE)->sum(fn (Schedule $schedule) => $schedule->expectedHours() * count($schedule->working_days ?? [])), 1),
            ])
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Client', 'Days', 'Time', 'From', 'To', 'Status'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with(['employee:id,name', 'client:id,client_name'])
            ->orderBy('start_date')
            ->get()
            ->sortBy(fn (Schedule $schedule) => $schedule->employee->name)
            ->map(fn (Schedule $schedule) => [
                $schedule->employee->name,
                $schedule->client?->client_name,
                collect($schedule->working_days ?? [])->map(fn (string $day) => Weekday::tryFrom($day)?->short() ?? $day)->implode(', '),
                "{$schedule->start_time}–{$schedule->end_time}",
                $schedule->start_date->toDateString(),
                $schedule->end_date?->toDateString() ?? 'Ongoing',
                ucfirst($schedule->status),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<Schedule>
     */
    private function query(ReportFilters $filters): Builder
    {
        return Schedule::query()
            ->whereDate('start_date', '<=', $filters->to)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $filters->from))
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
