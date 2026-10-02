<?php

namespace App\Actions\Workforce;

use App\Models\Rate;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;

/**
 * Ends a contractor's work for a client. The rate stays in history.
 */
class EndClientAssignment
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly SyncEmploymentType $syncEmploymentType,
    ) {}

    public function handle(Rate $rate, ?CarbonImmutable $endDate = null): Rate
    {
        $endDate ??= CarbonImmutable::today();

        // An assignment cannot end before it started.
        $rate->update(['end_date' => $endDate->max($rate->effective_date)]);

        $this->activity->log('workforce', 'Ended client assignment', $rate, "{$rate->employee->name} · {$rate->client->client_name}");
        $this->syncEmploymentType->handle($rate->employee);

        return $rate;
    }
}
