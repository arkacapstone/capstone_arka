<?php

namespace Database\Factories;

use App\Enums\CashAdvanceStatus;
use App\Models\CashAdvance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAdvance>
 */
class CashAdvanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // contractor_id is the user's id; employee_id is another name for it.
            'contractor_id' => fn (array $attributes) => $attributes['employee_id'] ?? User::factory(),
            'amount' => 3000,
            'repayment_months' => 2,
            'reason' => fake()->sentence(),
            'status' => CashAdvanceStatus::Pending,
        ];
    }
}
