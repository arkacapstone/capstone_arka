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
            'employee_id' => User::factory(),
            'amount' => 3000,
            'remaining_balance' => 3000,
            'reason' => fake()->sentence(),
            'status' => CashAdvanceStatus::Pending,
        ];
    }
}
