<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_the_password_change_a_new_contractor_is_sent_to_complete_their_profile(): void
    {
        $contractor = User::factory()->profileIncomplete()->create(['must_change_password' => true]);

        $this->actingAs($contractor)
            ->put(route('password.setup.update'), [
                'password' => 'a-new-Secure-passw0rd',
                'password_confirmation' => 'a-new-Secure-passw0rd',
            ])
            ->assertRedirect(route('profile.setup'));

        $this->actingAs($contractor)->get(route('dashboard'))->assertRedirect(route('profile.setup'));
        $this->actingAs($contractor)->get(route('profile.setup'))->assertInertia(fn (Assert $page) => $page->component('Auth/CompleteProfile'));
    }

    public function test_the_profile_needs_every_personal_detail(): void
    {
        $contractor = User::factory()->profileIncomplete()->create();

        $this->actingAs($contractor)
            ->put(route('profile.setup.update'), ['phone_number' => '09171234567'])
            ->assertSessionHasErrors(['birthday', 'address', 'emergency_contact_name', 'emergency_contact_number']);

        $this->assertTrue($contractor->refresh()->needsProfileSetup());
    }

    public function test_completing_the_profile_saves_the_details_and_opens_arka(): void
    {
        $admin = User::factory()->admin()->profileIncomplete()->create();

        $this->actingAs($admin)
            ->put(route('profile.setup.update'), [
                'phone_number' => '09171234567',
                'birthday' => '2000-05-14',
                'address' => '12 Rizal St., Cebu City',
                'emergency_contact_name' => 'Maria Santos',
                'emergency_contact_number' => '09181234567',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $admin->refresh();

        $this->assertFalse($admin->needsProfileSetup());
        $this->assertSame('12 Rizal St., Cebu City', $admin->address);
        $this->assertSame('Maria Santos', $admin->emergency_contact_name);

        $this->actingAs($admin)->get(route('profile.setup'))->assertRedirect(route('dashboard'));

        $this->travelTo('2026-09-27');
        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('account.age', 26)->where('account.address', '12 Rizal St., Cebu City'));
    }

    public function test_the_super_admin_is_never_asked_to_complete_a_profile(): void
    {
        $superAdmin = User::factory()->superAdmin()->profileIncomplete()->create();

        $this->actingAs($superAdmin)->get(route('dashboard'))->assertRedirect(route('super-admin.dashboard'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function birthdays(): array
    {
        return [
            'nine years old' => ['-9 years', false],
            'exactly ten today' => ['-10 years', true],
            'sixty-four' => ['-64 years', true],
            'turns sixty-five today' => ['-65 years', false],
            'seventy' => ['-70 years', false],
        ];
    }

    #[DataProvider('birthdays')]
    public function test_the_birthday_must_be_for_ages_ten_to_sixty_four(string $age, bool $accepted): void
    {
        $contractor = User::factory()->profileIncomplete()->create();

        $response = $this->actingAs($contractor)->put(route('profile.setup.update'), [
            'phone_number' => '09171234567',
            'birthday' => now()->modify($age)->toDateString(),
            'address' => '12 Rizal St., Cebu City',
            'emergency_contact_name' => 'Maria Santos',
            'emergency_contact_number' => '09181234567',
        ]);

        $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('birthday');
    }
}
