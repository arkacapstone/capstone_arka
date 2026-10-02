<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_land_on_the_admin_dashboard_after_login(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admins_cannot_open_money_related_or_super_admin_modules(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['super-admin.dashboard', 'super-admin.payroll', 'super-admin.cash-advances', 'super-admin.workforce.admins.index', 'super-admin.workforce.clients.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertForbidden();
        }
    }

    public function test_employees_and_the_super_admin_cannot_open_admin_modules(): void
    {
        foreach ([User::factory()->create(), User::factory()->superAdmin()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.scheduling.index'))->assertForbidden();
        }
    }

    public function test_every_admin_module_opens(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['admin.dashboard', 'admin.employees.index', 'admin.scheduling.index', 'admin.attendance.index', 'admin.devotionals.index', 'admin.reports.index', 'notifications.index', 'profile.edit'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }
}
