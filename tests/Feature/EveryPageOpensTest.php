<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Opens every page (GET route without parameters) as each role, with the seeded accounts,
 * and checks that none fails with a server error.
 */
class EveryPageOpensTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_opens_for_each_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['superadmin@arka.co' => UserRole::SuperAdmin, 'admin@arka.co' => UserRole::Admin, 'contractor@arka.co' => UserRole::Employee] as $email => $role) {
            $user = User::query()->where('email', $email)->sole();

            foreach ($this->pagesFor($role) as $uri) {
                $status = $this->actingAs($user)->get($uri)->getStatusCode();

                $this->assertLessThan(500, $status, "{$role->label()} → /{$uri} gave {$status}.");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pagesFor(UserRole $role): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{')
                && in_array('auth', $route->gatherMiddleware(), true)
                && ! str_contains($route->uri(), 'logout')
                && $this->allows($route, $role))
            ->map(fn (Route $route) => $route->uri())
            ->values()
            ->all();
    }

    private function allows(Route $route, UserRole $role): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                return in_array($role->value, explode(',', substr($middleware, 5)), true);
            }
        }

        return true;
    }
}
