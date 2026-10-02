<?php

namespace App\Services\Dashboard\Widgets\Admin;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

/**
 * Days with a time-in but no time-out, each with a "Fix this" link into the correction form.
 */
class IncompleteAttendanceWidget implements DashboardWidget
{
    private const LOOKBACK_DAYS = 14;

    private const LIMIT = 6;

    public function __construct(private readonly CarbonImmutable $today) {}

    public function key(): string
    {
        return 'incomplete';
    }

    /**
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    public function data(): array
    {
        $query = Attendance::query()
            ->where('status', AttendanceStatus::Incomplete)
            ->whereDate('date', '>=', $this->today->subDays(self::LOOKBACK_DAYS));

        return [
            'total' => (clone $query)->count(),
            'items' => $query
                ->with(['employee:id,name,employee_code', 'client:id,client_name'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Attendance $attendance) => [
                    'id' => $attendance->id,
                    'employee' => $attendance->employee->name,
                    'employeeCode' => $attendance->employee->employee_code,
                    'client' => $attendance->client?->client_name,
                    'date' => $attendance->date->toDateString(),
                    'timeIn' => $attendance->time_in?->format('g:i A'),
                    'fixUrl' => route('admin.attendance.index', ['fix' => $attendance->id, 'from' => $attendance->date->toDateString(), 'to' => $attendance->date->toDateString()]),
                ])
                ->all(),
        ];
    }
}
