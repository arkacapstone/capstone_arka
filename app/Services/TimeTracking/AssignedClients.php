<?php

namespace App\Services\TimeTracking;

use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The clients a contractor can run a timer for: every current client assignment (set by the
 * Super Admin) plus any client with an active schedule. One card per client (Contractor flow §III).
 */
class AssignedClients
{
    public function __construct(private readonly WorkDate $workDate) {}

    /**
     * @return Collection<int, array{client: Client, schedule: ?Schedule, date: CarbonImmutable, position: ?string}>
     */
    public function for(User $employee, CarbonImmutable $now): Collection
    {
        $fromRates = $employee->currentRates()->with('client')->get()->pluck('client');

        $schedules = $employee->schedules()
            ->active()
            ->applicableOn($now->startOfDay())
            ->with('client')
            ->orderByDesc('start_date')
            ->get();

        return $fromRates
            ->merge($schedules->pluck('client'))
            ->filter()
            ->unique('id')
            ->sortBy('client_name')
            ->values()
            ->map(function (Client $client) use ($employee, $now, $schedules) {
                [$date, $schedule] = $this->workDate->resolve($employee, $client->id, $now);

                return [
                    'client' => $client,
                    'schedule' => $schedule,
                    'date' => $date,
                    'position' => $schedule?->job_position ?? $schedules->firstWhere('client_id', $client->id)?->job_position,
                ];
            });
    }

    public function includes(User $employee, int $clientId, CarbonImmutable $now): bool
    {
        return $this->for($employee, $now)->contains(fn (array $row) => $row['client']->id === $clientId);
    }
}
