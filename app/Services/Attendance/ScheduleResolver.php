<?php

namespace App\Services\Attendance;

use App\Models\Schedule;
use Carbon\CarbonInterface;

/**
 * Finds the schedule that was actually in effect on a date (Blueprint §6):
 * attendance is always compared with the applicable period, never today's schedule.
 */
class ScheduleResolver
{
    public function for(int $employeeId, ?int $clientId, CarbonInterface $date): ?Schedule
    {
        return Schedule::query()
            ->forEmployee($employeeId)
            ->when($clientId, fn ($query) => $query->forClient($clientId))
            ->active()
            ->applicableOn($date)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->with('days')
            ->get()
            ->first(fn (Schedule $schedule) => $schedule->worksOn($date));
    }
}
