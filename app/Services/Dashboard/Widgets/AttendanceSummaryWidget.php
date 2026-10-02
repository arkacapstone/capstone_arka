<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

/**
 * Today's official attendance compared with the active workforce (Blueprint §7).
 */
class AttendanceSummaryWidget implements DashboardWidget
{
    /**
     * A running timer older than this is treated as a forgotten clock-out.
     * The window covers overnight/graveyard shifts that cross midnight.
     */
    private const OPEN_TIMER_LIMIT_HOURS = 24;

    public function __construct(private readonly CarbonImmutable $today) {}

    public function key(): string
    {
        return 'attendance';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $statusCounts = Attendance::query()
            ->whereDate('date', $this->today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $openTimerCutoff = CarbonImmutable::now()->subHours(self::OPEN_TIMER_LIMIT_HOURS);

        return [
            'date' => $this->today->toDateString(),
            'expected' => User::query()->workforce()->active()->count(),
            'accounted' => Attendance::query()->whereDate('date', $this->today)->distinct()->count('employee_id'),
            'breakdown' => array_map(fn (AttendanceStatus $status) => [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ], AttendanceStatus::cases()),
            'clockedIn' => TimeLog::query()->open()->where('time_in', '>=', $openTimerCutoff)->distinct()->count('employee_id'),
            'missingClockOut' => TimeLog::query()->open()->where('time_in', '<', $openTimerCutoff)->count(),
            'correctionsPending' => AttendanceCorrection::query()->pending()->count(),
        ];
    }
}
