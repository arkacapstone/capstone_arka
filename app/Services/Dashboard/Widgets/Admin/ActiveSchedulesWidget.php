<?php

namespace App\Services\Dashboard\Widgets\Admin;

use App\Models\Schedule;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

/**
 * Who is scheduled to be working right now, including graveyard shifts that started yesterday.
 */
class ActiveSchedulesWidget implements DashboardWidget
{
    public function __construct(private readonly CarbonImmutable $now) {}

    public function key(): string
    {
        return 'onShift';
    }

    /**
     * @return array{now: int, scheduledToday: int, byClient: list<array{client: string, count: int}>}
     */
    public function data(): array
    {
        $schedules = Schedule::query()
            ->active()
            ->applicableOn($this->now->subDay())
            ->orWhere(fn ($query) => $query->active()->applicableOn($this->now))
            ->with('client:id,client_name')
            ->get();

        $onShift = $schedules->filter(fn (Schedule $schedule) => $schedule->isOnShiftAt($this->now));

        return [
            'now' => $onShift->unique('employee_id')->count(),
            'scheduledToday' => $schedules
                ->filter(fn (Schedule $schedule) => $schedule->appliesOn($this->now) && $schedule->worksOn($this->now))
                ->unique('employee_id')
                ->count(),
            'byClient' => $onShift
                ->groupBy(fn (Schedule $schedule) => $schedule->client->client_name)
                ->map(fn ($group, string $client) => ['client' => $client, 'count' => $group->unique('employee_id')->count()])
                ->sortByDesc('count')
                ->values()
                ->all(),
        ];
    }
}
