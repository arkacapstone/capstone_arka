<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_profile_uses_the_shared_profile_page(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['employee_code' => 'ARKA-0001']);

        $this->actingAs($superAdmin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile')
                ->where('account.employeeCode', 'ARKA-0001')
                ->where('account.role', 'Super Admin')
                ->where('account.canEditEmail', true)
            );
    }

    public function test_super_admin_can_update_their_name_and_email(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->patch(route('profile.update'), ['name' => 'Reyzell Castillo', 'email' => 'owner@arka.co'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $superAdmin->refresh();

        $this->assertSame('Reyzell Castillo', $superAdmin->name);
        $this->assertSame('owner@arka.co', $superAdmin->email);
    }

    public function test_the_owner_account_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->delete('/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertModelExists($superAdmin);
    }
}
