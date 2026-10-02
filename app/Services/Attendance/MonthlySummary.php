<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A contractor's own attendance counts for a month (Contractor flow §VI "My Attendance Summary").
 * Counts are days, so two clients on one day still count as one Present day.
 */
class MonthlySummary
{
    /**
     * @return array{present: int, absent: int, late: int, overtime: int, paidLeave: int, unpaidLeave: int, incomplete: int}
     */
    public function for(User $employee, CarbonImmutable $month): array
    {
        $records = $employee->attendances()
            ->with('schedule')
            ->whereDate('date', '>=', $month->startOfMonth())
            ->whereDate('date', '<=', $month->endOfMonth())
            ->get();

        $days = fn (callable $filter) => $records->filter($filter)->map(fn (Attendance $record) => $record->date->toDateString())->unique()->count();
        $is = fn (AttendanceStatus ...$statuses) => fn (Attendance $record) => in_array($record->status, $statuses, true);

        return [
            'present' => $days($is(AttendanceStatus::Present, AttendanceStatus::Undertime, AttendanceStatus::Late)),
            'absent' => $days($is(AttendanceStatus::Absent)),
            'late' => $days($is(AttendanceStatus::Late)),
            'overtime' => $days(fn (Attendance $record) => $record->schedule !== null
                && $record->actual_hours !== null
                && (float) $record->actual_hours > $record->schedule->expectedHours()),
            'paidLeave' => $days($is(AttendanceStatus::PaidLeave)),
            'unpaidLeave' => $days($is(AttendanceStatus::UnpaidLeave)),
            'incomplete' => $days($is(AttendanceStatus::Incomplete)),
        ];
    }
}
