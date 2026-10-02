<?php

namespace Tests\Feature;

use App\Enums\Appearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_follow_the_system_appearance(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.appearance', 'system'));
    }

    public function test_every_role_can_save_an_appearance_that_persists(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user)
                ->patch(route('profile.appearance'), ['appearance' => 'dark'])
                ->assertRedirect();

            $this->assertSame(Appearance::Dark, $user->fresh()->appearance);

            $this->get(route('profile.edit'))
                ->assertInertia(fn (Assert $page) => $page->component('Account/Profile')->where('auth.user.appearance', 'dark'));
        }
    }

    public function test_the_saved_appearance_is_applied_before_the_first_paint(): void
    {
        $user = User::factory()->create(['appearance' => 'dark']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertSee('data-appearance="dark"', false);
    }

    public function test_only_light_dark_and_system_are_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('profile.appearance'), ['appearance' => 'neon'])
            ->assertSessionHasErrors('appearance');
    }
}
