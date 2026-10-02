<?php

namespace App\Actions\Workforce;

use App\Models\Rate;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a rate from an effective date. The old rate is ended the day before,
 * never overwritten, so earlier payroll keeps the rate that actually applied.
 */
class ChangeRate
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array{gross_pay: numeric, pay_frequency: string, working_days: int, hours_per_day: int, effective_date: string}  $terms
     */
    public function handle(Rate $current, array $terms): Rate
    {
        $effective = CarbonImmutable::parse($terms['effective_date']);

        if ($current->end_date !== null) {
            throw ValidationException::withMessages(['effective_date' => 'This assignment has already ended.']);
        }

        if ($effective->lessThanOrEqualTo($current->effective_date)) {
            throw ValidationException::withMessages([
                'effective_date' => 'The new rate must start after '.$current->effective_date->format('M j, Y').'.',
            ]);
        }

        return DB::transaction(function () use ($current, $terms, $effective) {
            $current->update(['end_date' => $effective->subDay()]);

            $next = $current->replicate(['end_date'])->fill([...$terms, 'end_date' => null]);
            $next->save();

            $this->activity->log(
                'workforce',
                'Changed client rate',
                $next,
                "{$current->employee->name} · {$current->client->client_name}: ₱".number_format((float) $current->gross_pay, 2).' → ₱'.number_format((float) $next->gross_pay, 2),
            );

            return $next;
        });
    }
}
