<?php

namespace App\Services\Payroll;

use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;

/**
 * Finds the payroll period that applies to a given date.
 *
 * A period stored by the Super Admin always wins. When none has been created yet,
 * an unsaved period is projected from the semi-monthly cycle in System & Rules
 * (default Blueprint §8: cutoff on the 10th and 25th, salary release on the 15th and 30th).
 */
class PayrollPeriodResolver
{
    public function __construct(private readonly ?SystemRules $rules = null) {}

    /**
     * A cutoff rule from System & Rules, or its default when used without the container.
     */
    private function day(string $rule): int
    {
        return $this->rules?->integer($rule) ?? (int) SystemRules::definitions()[$rule]['default'];
    }

    public function current(?CarbonImmutable $today = null): PayrollPeriod
    {
        $today ??= CarbonImmutable::today();

        return PayrollPeriod::query()
            ->covering($today)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first()
            ?? $this->projectSemiMonthly($today);
    }

    public function projectSemiMonthly(CarbonImmutable $today): PayrollPeriod
    {
        $firstCutoff = $this->day('first_cutoff_day');
        $secondCutoff = max($firstCutoff + 1, $this->day('second_cutoff_day'));
        $day = fn (CarbonImmutable $month, int $day) => $month->setDay(min($day, $month->daysInMonth));
        $thisMonth = $today->startOfMonth();

        [$start, $end, $releaseDay] = match (true) {
            $today->day <= $firstCutoff => [
                $day($thisMonth->subMonthNoOverflow(), $secondCutoff)->addDay(),
                $day($thisMonth, $firstCutoff),
                $this->day('first_release_day'),
            ],
            $today->day <= $day($thisMonth, $secondCutoff)->day => [
                $day($thisMonth, $firstCutoff)->addDay(),
                $day($thisMonth, $secondCutoff),
                $this->day('second_release_day'),
            ],
            default => [
                $day($thisMonth, $secondCutoff)->addDay(),
                $day($thisMonth->addMonthNoOverflow(), $firstCutoff),
                $this->day('first_release_day'),
            ],
        };

        // Release falls in the cutoff's month, or the following one if that day has already passed.
        $release = $day($end->startOfMonth(), $releaseDay);
        if ($release->lessThan($end)) {
            $release = $day($end->startOfMonth()->addMonthNoOverflow(), $releaseDay);
        }

        return new PayrollPeriod([
            'period_name' => $start->format('M j').' – '.$end->format('M j, Y'),
            'start_date' => $start,
            'end_date' => $end,
            'cutoff_date' => $end,
            'release_date' => $release,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'status' => $today->isSameDay($end) ? PayrollPeriodStatus::Verification : PayrollPeriodStatus::Open,
        ]);
    }
}
