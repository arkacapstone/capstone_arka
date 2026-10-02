<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_workforce_opens_on_the_admin_list(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce'))
            ->assertRedirect(route('super-admin.workforce.admins.index'));
    }

    public function test_only_admins_are_listed_and_counted(): void
    {
        User::factory()->admin()->count(2)->create();
        User::factory()->admin()->inactive()->create();
        User::factory()->count(4)->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.admins.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Workforce/Admins')
                ->has('admins.data', 3)
                ->where('counts', ['total' => 3, 'active' => 2, 'inactive' => 1])
            );
    }

    public function test_admins_can_be_searched_and_filtered_by_status(): void
    {
        User::factory()->admin()->create(['name' => 'Maria Santos']);
        User::factory()->admin()->inactive()->create(['name' => 'Maria Cruz']);
        User::factory()->admin()->create(['name' => 'John Reyes']);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.admins.index', ['search' => 'maria', 'status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('admins.data', 1)
                ->where('admins.data.0.name', 'Maria Santos')
            );
    }

    public function test_super_admin_invites_an_admin_the_same_way_as_a_contractor(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)->post(route('super-admin.workforce.admins.store'), [
            'name' => 'Ana Lim',
            'email' => 'ana.lim@gmail.com',
        ]);

        $response->assertRedirect(route('super-admin.workforce.admins.index'))
            ->assertSessionHas('success');

        $admin = User::query()->where('email', 'ana.lim@gmail.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->isInvited());
        $this->assertMatchesRegularExpression('/^ARKA-\d{4}$/', $admin->employee_code);
        $this->assertDatabaseHas(ActivityLog::class, ['module' => 'workforce', 'action' => 'Invited Admin', 'reference_id' => $admin->id]);
        Notification::assertSentTo($admin, AccountInvitation::class);
    }

    public function test_company_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@arka.co']);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.workforce.admins.store'), ['name' => 'Someone', 'email' => 'taken@arka.co'])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_updates_admin_details(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.workforce.admins.update', $admin), [
                'name' => 'Renamed Admin',
                'email' => $admin->email,
                'phone_number' => '09998887777',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Admin', $admin->refresh()->name);
        $this->assertSame('09998887777', $admin->phone_number);
    }

    public function test_deactivated_admins_cannot_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.workforce.admins.status', $admin), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $admin->refresh()->status);

        auth()->logout();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_admin_deactivated_mid_session_is_signed_out(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_employee_and_super_admin_accounts_cannot_be_managed_as_admins(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.workforce.admins.status', $employee), ['status' => 'inactive'])
            ->assertNotFound();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.workforce.admins.invitation', $this->superAdmin))
            ->assertNotFound();
    }

    public function test_admins_cannot_manage_other_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('super-admin.workforce.admins.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('super-admin.workforce.admins.store'), ['name' => 'X', 'email' => 'x@arka.co'])
            ->assertForbidden();
    }

    public function test_temporary_password_must_be_changed_before_using_arka(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertRedirect(route('password.setup'));

        $this->actingAs($admin)->get(route('password.setup'))->assertOk();

        $this->actingAs($admin)
            ->put(route('password.setup.update'), [
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->put(route('password.setup.update'), [
                'password' => 'a-new-Secure-passw0rd',
                'password_confirmation' => 'a-new-Secure-passw0rd',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($admin->refresh()->must_change_password);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}
