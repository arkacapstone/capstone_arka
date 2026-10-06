<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViewModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_land_on_the_employee_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_an_admin_switches_to_their_employee_view_and_back_without_signing_in_again(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('view-mode.switch'), ['mode' => 'employee'])
            ->assertRedirect(route('employee.dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('employee.dashboard'));

        $this->get(route('employee.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('viewMode', 'employee')
                ->where('canSwitchView', true)
                ->where('navigation.1.key', 'time-tracker')
                ->where('navigation.1.children.0.key', 'time-history')
                ->where('navigation.2.key', 'my-attendance')
            );

        $this->post(route('view-mode.switch'), ['mode' => 'admin'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('viewMode', 'admin')->where('navigation.2.key', 'employees'));
    }

    public function test_opening_a_page_puts_the_admin_in_the_matching_view(): void
    {
        $admin = User::factory()->admin()->create();

        // The very first Employee page already shows the Employee sidebar.
        $this->actingAs($admin)
            ->get(route('employee.time-tracker.index'))
            ->assertInertia(fn (Assert $page) => $page->where('viewMode', 'employee')->where('navigation.1.key', 'time-tracker'));
        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('viewMode', 'employee'));

        $this->get(route('admin.employees.index'))
            ->assertInertia(fn (Assert $page) => $page->where('viewMode', 'admin')->where('navigation.2.key', 'employees'));
        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('viewMode', 'admin'));
    }

    public function test_only_admins_can_switch_views(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('view-mode.switch'), ['mode' => 'admin'])
            ->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('view-mode.switch'), ['mode' => 'employee'])
            ->assertForbidden();
    }

    public function test_the_super_admin_has_no_employee_portal_and_employees_have_no_admin_portal(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('employee.dashboard'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_every_employee_module_opens_for_employees_and_admins(): void
    {
        $routes = ['employee.dashboard', 'employee.time-tracker.index', 'employee.time-history.index', 'employee.devotionals.index', 'employee.attendance.index', 'employee.leave.index', 'employee.payslips.index', 'employee.cash-advances.index'];

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            foreach ($routes as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }
        }
    }
}
