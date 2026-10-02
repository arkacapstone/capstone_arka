<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function unlockSecurity(): void
    {
        $this->post(route('profile.security.unlock'), ['password' => 'password'])->assertRedirect(route('profile.security'));
    }

    public function test_every_settings_section_opens_for_every_role(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user);

            foreach (['profile.edit' => 'profile', 'profile.appearance.edit' => 'appearance'] as $route => $section) {
                $this->get(route($route))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->component('Account/Profile')->where('section', $section));
            }

            $this->unlockSecurity();
            $this->get(route('profile.security'))->assertInertia(fn (Assert $page) => $page->where('section', 'security'));
        }
    }

    public function test_opening_security_always_asks_for_the_password_first(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('profile.security'))->assertRedirect(route('profile.security.confirm'));
        $this->get(route('profile.security.confirm'))->assertInertia(fn (Assert $page) => $page->where('section', 'confirm'));

        $this->post(route('profile.security.unlock'), ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->get(route('profile.security'))->assertRedirect(route('profile.security.confirm'));

        $this->unlockSecurity();
        $this->get(route('profile.security'))->assertOk();
        $this->get(route('profile.security'))->assertOk(); // reloads and passkey refreshes stay unlocked

        // Opening any other page locks it again, so the next visit asks again.
        $this->get(route('profile.edit'));
        $this->get(route('profile.security'))->assertRedirect(route('profile.security.confirm'));
    }

    public function test_background_requests_do_not_lock_security(): void
    {
        $this->actingAs(User::factory()->create());
        $this->unlockSecurity();

        $this->getJson(route('notifications.feed'))->assertOk();

        $this->get(route('profile.security'))->assertOk();
    }

    public function test_security_locks_again_after_fifteen_minutes(): void
    {
        $this->actingAs(User::factory()->create());
        $this->unlockSecurity();

        $this->travel(16)->minutes();

        $this->get(route('profile.security'))->assertRedirect(route('profile.security.confirm'));
    }

    public function test_the_security_section_lists_the_users_passkeys(): void
    {
        $user = User::factory()->create();
        $user->passkeys()->create(['name' => 'Work laptop', 'credential_id' => 'abc123', 'credential' => ['id' => 'abc123']]);

        $this->actingAs($user);
        $this->unlockSecurity();

        $this->get(route('profile.security'))
            ->assertInertia(fn (Assert $page) => $page->has('passkeys', 1)->where('passkeys.0.name', 'Work laptop'));
    }

    public function test_confirming_for_security_also_allows_managing_passkeys(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('passkey.registration-options'))->assertStatus(423);

        $this->unlockSecurity();

        $this->getJson(route('passkey.registration-options'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
    }

    public function test_the_sidebar_profile_item_stays_active_on_every_settings_tab(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($items) => collect($items)->firstWhere('key', 'profile')['match'] === 'profile.*'));
    }
}
