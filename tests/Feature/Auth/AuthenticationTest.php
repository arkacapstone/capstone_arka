<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_returns_to_the_page_opened_before_signing_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get(route('admin.employees.index'))->assertRedirect(route('login'));

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.employees.index'));
    }

    public function test_login_skips_a_saved_page_the_role_cannot_open(): void
    {
        // A Super Admin page was left open; an Admin signs in on that browser.
        $admin = User::factory()->admin()->create();

        $this->get(route('super-admin.payroll'))->assertRedirect(route('login'));

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_super_admin_log_in_admits_super_admins(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $superAdmin->email, 'password' => 'password', 'portal' => 'super_admin'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_the_super_admin_log_in_turns_away_other_roles(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password', 'portal' => 'super_admin'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sign_up_only_makes_a_new_home_office_super_admin(): void
    {
        // "Register your home office" needs a home office name; it never adds a Contractor or
        // Admin to an existing home office (only its Super Admin and Admins create those).
        $this->post('/register', ['name' => 'Someone', 'email' => 'someone@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('home_office_name');

        $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
