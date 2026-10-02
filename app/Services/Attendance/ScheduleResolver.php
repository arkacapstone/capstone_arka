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
            ->where('employee_id', $employeeId)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->active()
            ->applicableOn($date)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Schedule $schedule) => $schedule->worksOn($date));
    }
}
