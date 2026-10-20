<?php

namespace Database\Factories;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\Client;
use App\Models\User;
use Database\Factories\Concerns\ForAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    use ForAssignment;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => fn (array $attributes) => isset($attributes['attendance_id']) ? null : User::factory(),
            'client_id' => fn (array $attributes) => isset($attributes['attendance_id']) ? null : Client::factory(),
            'date' => today(),
            'source' => 'employee',
            'field_corrected' => 'time_out',
            'requested_time_out' => '18:00',
            'reason' => fake()->sentence(),
            'status' => CorrectionStatus::Pending,
        ];
    }
}
