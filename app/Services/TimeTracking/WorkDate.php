<?php

namespace App\Services\TimeTracking;

use App\Models\Schedule;
use App\Models\User;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;

/**
 * The attendance date a timer belongs to. An overnight shift is one work period (Blueprint §7):
 * a timer started at 1:00 AM during last night's 10 PM – 6 AM shift belongs to yesterday.
 */
class WorkDate
{
    public function __construct(private readonly ScheduleResolver $schedules) {}

    /**
     * @return array{0: CarbonImmutable, 1: ?Schedule}
     */
    public function resolve(User $employee, int $clientId, CarbonImmutable $now): array
    {
        $today = $now->startOfDay();
        $yesterday = $today->subDay();
        $overnight = $this->schedules->for($employee->id, $clientId, $yesterday);

        if ($overnight?->crossesMidnight() && $now->lessThan($overnight->windowOn($yesterday)[1])) {
            return [$yesterday, $overnight];
        }

        return [$today, $this->schedules->for($employee->id, $clientId, $today)];
    }
}
