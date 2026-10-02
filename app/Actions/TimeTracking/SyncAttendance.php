<?php

namespace App\Actions\TimeTracking;

use App\Enums\CorrectionStatus;
use App\Models\Attendance;
use App\Models\TimeLog;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceLock;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;

/**
 * Timers generate attendance (Blueprint §7): the day's first time-in and last time-out
 * for a client become that client's attendance record, compared with the schedule in effect.
 *
 * Locked days and records an Admin already corrected are left untouched, so a
 * correction is never silently overwritten by a later timer.
 */
class SyncAttendance
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly AttendanceCalculator $calculator,
        private readonly AttendanceLock $lock,
    ) {}

    public function handle(int $employeeId, int $clientId, CarbonImmutable $date): ?Attendance
    {
        if ($this->lock->isLocked($date)) {
            return null;
        }

        $logs = TimeLog::query()
            ->where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->whereDate('date', $date)
            ->orderBy('time_in')
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $attendance = Attendance::query()
            ->where('employee_id', $employeeId)
            ->where('client_id', $clientId)
            ->whereDate('date', $date)
            ->first() ?? new Attendance(['employee_id' => $employeeId, 'client_id' => $clientId, 'date' => $date]);

        if ($attendance->exists && $attendance->corrections()->where('status', CorrectionStatus::Approved)->exists()) {
            return $attendance;
        }

        $timeIn = $logs->first()->time_in;
        $timeOut = $logs->contains(fn (TimeLog $log) => $log->isOpen()) ? null : $logs->max('time_out');
        $breakMinutes = (int) $logs->sum('break_minutes');
        $schedule = $this->schedules->for($employeeId, $clientId, $date);

        $values = $this->calculator->calculate($schedule, $date, $timeIn, $timeOut, $breakMinutes);

        // Separate sessions count only the time actually tracked, not the gaps between them.
        // Time before the scheduled start (the timer may start a few minutes early) is not counted.
        if ($timeOut !== null) {
            $shiftStart = $schedule ? CarbonImmutable::instance($schedule->windowOn($date)[0]) : null;

            $values['actual_hours'] = round($logs->sum(function (TimeLog $log) use ($shiftStart) {
                $in = CarbonImmutable::instance($log->time_in);
                $out = CarbonImmutable::instance($log->time_out);
                $early = $shiftStart && $in->lessThan($shiftStart) ? $in->diffInSeconds($out->min($shiftStart)) : 0;

                return max(0, $log->workedSecondsAt($log->time_out) - $early);
            }) / 3600, 2);
        }

        $attendance->fill([
            'schedule_id' => $schedule?->id,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'break_minutes' => $breakMinutes,
            ...$values,
        ])->save();

        return $attendance;
    }
}
