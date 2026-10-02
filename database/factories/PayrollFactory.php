<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Client;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults mirror the Blueprint §11 worked example:
 * ₱20,000 gross, 1 day absent, 2 late hours, 5 additional hours → ₱19,375 net.
 *
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_id' => PayrollPeriod::factory(),
            'employee_id' => User::factory(),
            'client_id' => Client::factory(),
            'rate_id' => fn (array $attributes) => Rate::factory()->create([
                'employee_id' => $attributes['employee_id'],
                'client_id' => $attributes['client_id'],
            ]),
            'gross_pay' => 20000,
            'hourly_rate' => 125,
            'daily_rate' => 1000,
            'additional_hours' => 5,
            'additional_pay' => 625,
            'days_absent' => 1,
            'absence_deduction' => 1000,
            'late_hours' => 2,
            'late_deduction' => 250,
            'net_pay' => 19375,
            'status' => PayrollStatus::Draft,
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PayrollStatus::Reviewed]);
    }
}
