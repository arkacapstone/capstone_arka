<?php

namespace Database\Factories;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'date' => today(),
            'source' => 'employee',
            'field_corrected' => 'time_out',
            'requested_time_out' => '18:00',
            'reason' => fake()->sentence(),
            'status' => CorrectionStatus::Pending,
        ];
    }
}
