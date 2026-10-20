<?php

namespace Database\Factories;

use App\Models\Devotional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Devotional>
 */
class DevotionalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // contractor_id is the user's id; employee_id is another name for it.
            'contractor_id' => fn (array $attributes) => $attributes['employee_id'] ?? User::factory(),
            'date' => today()->toDateString(),
            'title' => fake()->sentence(3),
            'file_path' => 'devotionals/'.fake()->uuid().'.pdf',
            'file_name' => 'devotional.pdf',
            'file_size' => 120_000,
            'submitted_at' => now(),
        ];
    }
}
