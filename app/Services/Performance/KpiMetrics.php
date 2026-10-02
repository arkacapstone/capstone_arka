<?php

namespace App\Services\Performance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Suggests a KPI score from the records ARKA already keeps (Blueprint §9: devotional compliance
 * may count as a positive factor). The Super Admin decides the final score — this only helps.
 *
 *   Attendance   40% — days present out of days that were scheduled (leave excluded)
 *   Punctuality  30% — days on time out of days present (no grace period)
 *   Devotional   30% — days with a devotional submitted out of the days in the range
 */
class KpiMetrics
{
    /**
     * @return array{attendance: ?float, punctuality: ?float, devotional: ?float, suggested: ?float, daysPresent: int, daysLate: int, daysAbsent: int, devotionals: int, days: int}
     */
    public function for(User $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $to = $to->min(CarbonImmutable::today());
        $days = $to->lessThan($from) ? 0 : (int) $from->diffInDays($to) + 1;

        // One outcome per day, even when the contractor serves several clients.
        $byDay = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get()
            ->groupBy(fn (Attendance $record) => $record->date->toDateString())
            ->map(fn ($records) => $records->pluck('status'));

        $worked = $byDay->reject(fn ($statuses) => $statuses->every(fn (AttendanceStatus $status) => $status->isLeave()));
        $absent = $worked->filter(fn ($statuses) => $statuses->every(fn (AttendanceStatus $status) => $status === AttendanceStatus::Absent))->count();
        $present = $worked->count() - $absent;
        $late = $worked->filter(fn ($statuses) => $statuses->contains(AttendanceStatus::Late))->count();

        $devotionals = $employee->devotionals()->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->count();

        $attendance = $worked->isEmpty() ? null : round($present / $worked->count() * 100, 1);
        $punctuality = $present === 0 ? null : round(($present - $late) / $present * 100, 1);
        $devotional = $days === 0 ? null : round(min(100, $devotionals / $days * 100), 1);

        $weights = array_filter(['attendance' => [$attendance, 0.4], 'punctuality' => [$punctuality, 0.3], 'devotional' => [$devotional, 0.3]], fn ($pair) => $pair[0] !== null);
        $weightTotal = array_sum(array_column($weights, 1));
        $suggested = $weightTotal > 0 ? round(array_sum(array_map(fn ($pair) => $pair[0] * $pair[1], $weights)) / $weightTotal, 1) : null;

        return [
            'attendance' => $attendance,
            'punctuality' => $punctuality,
            'devotional' => $devotional,
            'suggested' => $suggested,
            'daysPresent' => $present,
            'daysLate' => $late,
            'daysAbsent' => $absent,
            'devotionals' => $devotionals,
            'days' => $days,
        ];
    }
}
