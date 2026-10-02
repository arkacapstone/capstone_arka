<?php

namespace Database\Factories;

use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_name' => 'Sep 11 – Sep 25, 2026',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'cutoff_date' => '2026-09-25',
            'release_date' => '2026-09-30',
            'pay_frequency' => PayFrequency::SemiMonthly,
            'status' => PayrollPeriodStatus::Open,
        ];
    }
}
