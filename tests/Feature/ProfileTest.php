<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_admins_use_the_shared_profile_and_security_page(): void
    {
        $this->actingAs(User::factory()->admin()->create(['employee_code' => 'ARKA-0042']))
            ->get('/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile')
                ->where('account.employeeCode', 'ARKA-0042')
                ->where('account.canEditEmail', false)
            );
    }

    public function test_personal_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'birthday' => '1998-04-12',
                'phone_number' => '09171234567',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('1998-04-12', $user->birthday->toDateString());
        $this->assertSame('09171234567', $user->phone_number);
    }

    public function test_employees_cannot_change_their_company_email(): void
    {
        $user = User::factory()->create(['email' => 'issued@arka.co']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Test User', 'email' => 'personal@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('issued@arka.co', $user->refresh()->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_accounts_cannot_be_deleted_from_the_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }
}
