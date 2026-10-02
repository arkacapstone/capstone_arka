<?php

namespace Database\Factories;

use App\Enums\PayFrequency;
use App\Models\Client;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rate>
 */
class RateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'client_id' => Client::factory(),
            'gross_pay' => 20000,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'working_days' => 11,
            'hours_per_day' => 8,
            'effective_date' => now()->startOfYear(),
        ];
    }
}
