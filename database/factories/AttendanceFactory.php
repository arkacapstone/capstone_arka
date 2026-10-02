<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'client_id' => Client::factory(),
            'date' => today(),
            'status' => AttendanceStatus::Present,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
