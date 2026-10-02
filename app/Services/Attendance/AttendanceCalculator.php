<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns a schedule and actual time-in / time-out into the official attendance values (Blueprint §7):
 * Schedule → Time In → Time Out → Actual Hours → Late Minutes → Attendance Record.
 *
 * No grace period: even one minute past the scheduled start is Late.
 */
class AttendanceCalculator
{
    /**
     * @return array{actual_hours: ?float, late_minutes: int, undertime_minutes: int, status: AttendanceStatus}
     */
    public function calculate(?Schedule $schedule, CarbonInterface $date, ?CarbonInterface $timeIn, ?CarbonInterface $timeOut, int $breakMinutes = 0): array
    {
        if ($timeIn === null) {
            return ['actual_hours' => null, 'late_minutes' => 0, 'undertime_minutes' => 0, 'status' => AttendanceStatus::Absent];
        }

        if ($timeOut === null) {
            return ['actual_hours' => null, 'late_minutes' => $this->lateMinutes($schedule, $date, $timeIn), 'undertime_minutes' => 0, 'status' => AttendanceStatus::Incomplete];
        }

        // Starting the timer a few minutes early is allowed, but work counts from the scheduled start.
        $countedFrom = $schedule ? CarbonImmutable::instance($timeIn)->max($schedule->windowOn($date)[0]) : CarbonImmutable::instance($timeIn);
        $workedMinutes = max(0, $countedFrom->diffInMinutes($timeOut, false) - $breakMinutes);
        $late = $this->lateMinutes($schedule, $date, $timeIn);
        $undertime = $this->undertimeMinutes($schedule, $date, $timeOut);

        return [
            'actual_hours' => round($workedMinutes / 60, 2),
            'late_minutes' => $late,
            'undertime_minutes' => $undertime,
            'status' => match (true) {
                $late > 0 => AttendanceStatus::Late,
                $undertime > 0 => AttendanceStatus::Undertime,
                default => AttendanceStatus::Present,
            },
        ];
    }

    private function lateMinutes(?Schedule $schedule, CarbonInterface $date, CarbonInterface $timeIn): int
    {
        if ($schedule === null) {
            return 0;
        }

        [$start] = $schedule->windowOn($date);
        $timeIn = CarbonImmutable::instance($timeIn)->startOfMinute();

        return $timeIn->greaterThan($start) ? (int) $start->diffInMinutes($timeIn) : 0;
    }

    private function undertimeMinutes(?Schedule $schedule, CarbonInterface $date, CarbonInterface $timeOut): int
    {
        if ($schedule === null) {
            return 0;
        }

        [, $end] = $schedule->windowOn($date);
        $timeOut = CarbonImmutable::instance($timeOut)->startOfMinute();

        return $timeOut->lessThan($end) ? (int) $timeOut->diffInMinutes($end) : 0;
    }
}
