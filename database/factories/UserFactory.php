<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\HomeOffice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'ARKA-'.fake()->unique()->numerify('######'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Employee,
            'status' => UserStatus::Active->value,
            'profile_completed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A home office has one Super Admin, so another Super Admin gets a home office of their own.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SuperAdmin,
            'home_office_id' => fn () => User::query()->where('role', UserRole::SuperAdmin)->where('home_office_id', HomeOffice::currentId())->exists()
                ? HomeOffice::query()->create(['name' => 'Home Office '.Str::random(8)])->id
                : HomeOffice::currentId(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Full-Time / Part-Time follows the contractor's clients, so this gives them a Part-Time client.
     */
    public function partTime(): static
    {
        return $this->afterCreating(fn (User $user) => ClientAssignment::query()->create([
            'contractor_id' => $user->id,
            'client_id' => Client::factory()->create()->id,
            'employment_type' => EmploymentType::PartTime,
            'start_date' => now()->toDateString(),
        ]));
    }

    /**
     * A new account whose owner has not filled in their personal details yet.
     */
    public function profileIncomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_completed_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Inactive->value,
        ]);
    }
}
