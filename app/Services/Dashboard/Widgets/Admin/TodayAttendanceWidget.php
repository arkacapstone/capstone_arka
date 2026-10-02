<?php

namespace App\Services\Dashboard\Widgets\Admin;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

class TodayAttendanceWidget implements DashboardWidget
{
    public function __construct(private readonly CarbonImmutable $today) {}

    public function key(): string
    {
        return 'attendance';
    }

    /**
     * @return array{date: string, present: int, late: int, absent: int, incomplete: int, onLeave: int}
     */
    public function data(): array
    {
        $counts = Attendance::query()
            ->whereDate('date', $this->today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $count = fn (AttendanceStatus ...$statuses) => (int) collect($statuses)->sum(fn (AttendanceStatus $status) => $counts[$status->value] ?? 0);

        return [
            'date' => $this->today->toDateString(),
            'present' => $count(AttendanceStatus::Present, AttendanceStatus::Undertime),
            'late' => $count(AttendanceStatus::Late),
            'absent' => $count(AttendanceStatus::Absent),
            'incomplete' => $count(AttendanceStatus::Incomplete),
            'onLeave' => $count(AttendanceStatus::PaidLeave, AttendanceStatus::UnpaidLeave),
        ];
    }
}
