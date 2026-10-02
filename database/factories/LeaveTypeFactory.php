<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_type_name' => fake()->randomElement(['Vacation Leave', 'Sick Leave', 'Emergency Leave']),
            'is_paid' => true,
            'requires_proof' => false,
        ];
    }
}
