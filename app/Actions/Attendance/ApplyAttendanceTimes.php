<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Writes corrected clock times onto the official attendance record and recalculates
 * hours, lateness, undertime and status against the schedule in effect that day.
 */
class ApplyAttendanceTimes
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * Times are "HH:MM" on the attendance date; a time-out earlier than the time-in
     * belongs to the next day (overnight shift).
     */
    public function handle(User $employee, CarbonImmutable $date, ?Attendance $attendance, ?string $timeIn, ?string $timeOut): Attendance
    {
        $attendance ??= $this->newAttendance($employee, $date);

        $in = $this->at($date, $timeIn) ?? $attendance->time_in;
        $out = $this->at($date, $timeOut) ?? $attendance->time_out;

        if ($in && $out && $out->lessThanOrEqualTo($in)) {
            $out = CarbonImmutable::instance($out)->addDay();
        }

        $schedule = $this->schedules->for($employee->id, $attendance->client_id, $date);
        $values = $this->calculator->calculate($schedule, $date, $in, $out, (int) $attendance->break_minutes);

        $attendance->fill([
            'schedule_id' => $schedule?->id ?? $attendance->schedule_id,
            'time_in' => $in,
            'time_out' => $out,
            ...$values,
        ])->save();

        return $attendance;
    }

    private function newAttendance(User $employee, CarbonImmutable $date): Attendance
    {
        $clientId = $this->schedules->for($employee->id, null, $date)?->client_id
            ?? $employee->currentRates()->value('client_id');

        if ($clientId === null) {
            throw ValidationException::withMessages([
                'time_in' => "{$employee->name} has no client assignment or schedule for this date, so there is no attendance to correct.",
            ]);
        }

        return new Attendance([
            'employee_id' => $employee->id,
            'client_id' => $clientId,
            'date' => $date,
            'break_minutes' => 0,
        ]);
    }

    private function at(CarbonImmutable $date, ?string $time): ?CarbonImmutable
    {
        return $time ? $date->setTimeFromTimeString($time) : null;
    }
}
