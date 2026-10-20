<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\HomeOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Register your home office: a new workforce company signs up from the login page.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = [
        'home_office_name' => 'Harbor Staffing',
        'name' => 'Hana Reyes',
        'email' => 'hana@harbor.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    public function test_the_register_page_is_the_login_page_with_the_register_card_open(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('startWith', 'register'));
    }

    public function test_registering_makes_a_home_office_and_its_super_admin(): void
    {
        HomeOffice::query()->create(['name' => 'Makarius Virtual Solutions']);

        $this->post('/register', [...self::FORM, 'home_office_address' => 'Cebu City, Philippines'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $homeOffice = HomeOffice::query()->where('name', 'Harbor Staffing')->sole();
        $this->assertSame('Cebu City, Philippines', $homeOffice->address);
        $user = User::query()->where('email', 'hana@harbor.test')->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertSame($homeOffice->id, $user->home_office_id);
        $this->assertSame('ARKA-0001', $user->employee_code);
        $this->assertSame(2, HomeOffice::query()->count());
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'Registered home office']);
    }

    public function test_a_home_office_name_can_only_be_registered_once_in_any_letter_case(): void
    {
        HomeOffice::query()->create(['name' => 'Harbor Staffing']);

        $this->post('/register', [...self::FORM, 'home_office_name' => 'harbor staffing'])
            ->assertSessionHasErrors('home_office_name');

        $this->assertGuest();
        $this->assertSame(1, HomeOffice::query()->count());
    }

    public function test_an_email_already_used_in_any_home_office_is_refused(): void
    {
        User::factory()->create(['email' => 'hana@harbor.test']);

        $this->post('/register', self::FORM)->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('home_offices', ['name' => 'Harbor Staffing']);
    }

    public function test_every_field_is_required_and_the_password_must_be_confirmed(): void
    {
        $this->post('/register', [...self::FORM, 'home_office_name' => '', 'name' => '', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors(['home_office_name', 'name', 'password']);
    }

    public function test_signed_in_users_cannot_open_the_register_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect();
    }
}
