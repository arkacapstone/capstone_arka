<?php

namespace Database\Factories;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addDay(),
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::PendingApproval,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LeaveRequestStatus::Approved]);
    }
}
