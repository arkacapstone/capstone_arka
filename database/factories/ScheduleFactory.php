<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 9:00 AM – 6:00 PM weekday schedule.
 *
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'client_id' => Client::factory(),
            'job_position' => 'Virtual Assistant',
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'status' => Schedule::STATUS_ACTIVE,
        ];
    }

    /**
     * 10:00 PM – 6:00 AM, every day.
     */
    public function graveyard(): static
    {
        return $this->state(fn (array $attributes) => [
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);
    }
}
