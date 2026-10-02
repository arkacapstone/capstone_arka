<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeLog>
 */
class TimeLogFactory extends Factory
{
    /**
     * A timer that is still running.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'client_id' => Client::factory(),
            'date' => today(),
            'time_in' => now()->subHour(),
            'status' => 'running',
        ];
    }

    /**
     * A timer that was started days ago and never stopped.
     */
    public function forgotten(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => today()->subDays(2),
            'time_in' => now()->subDays(2),
        ]);
    }
}
