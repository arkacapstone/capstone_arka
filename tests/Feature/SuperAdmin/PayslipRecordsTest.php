<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Client;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayslipRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_explains_where_payslips_come_from_before_any_payroll_exists(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.payslips'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Payslips/Index')->where('period', null));
    }

    public function test_released_payslips_are_listed_one_per_employee_and_fully_itemized(): void
    {
        $period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Released]);
        $employee = User::factory()->create(['name' => 'Christian Mae']);

        foreach (['Aurora Dental' => 25000, 'Northline' => 12000] as $name => $gross) {
            Payroll::factory()->for($employee, 'employee')->for($period, 'period')->for(Client::factory()->create(['client_name' => $name]))->create(['status' => PayrollStatus::Released, 'gross_pay' => $gross]);
        }

        // A fixed name keeps the alphabetical order stable.
        Payroll::factory()->for($period, 'period')->for(User::factory()->create(['name' => 'Zoe Reyes']), 'employee')->create(['status' => PayrollStatus::Released]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.payslips'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.released', true)
                ->where('summary.count', 2)
                ->where('payslips.0.employee.name', 'Christian Mae')
                ->where('payslips.0.clients', ['Aurora Dental', 'Northline'])
                ->where('payslips.0.net', 38750)
                ->where('payslips.0.status', 'available')
                ->where('payslips.0.earnings.1.label', fn (string $label) => str_starts_with($label, 'Additional Pay (Northline'))
            );
    }

    public function test_the_super_admin_can_preview_payslips_before_release_and_search_them(): void
    {
        $period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Locked]);
        Payroll::factory()->for($period, 'period')->for(User::factory()->create(['name' => 'Ana Reyes']), 'employee')->create();
        Payroll::factory()->for($period, 'period')->for(User::factory()->create(['name' => 'Ben Cruz']), 'employee')->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.payslips', ['search' => 'Ana']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.released', false)
                ->has('payslips', 1)
                ->where('payslips.0.status', 'processing')
                ->where('payslips.0.net', 19375) // previewed figures
            );
    }

    public function test_open_periods_have_no_payslips_and_admins_cannot_see_the_module(): void
    {
        PayrollPeriod::factory()->create(); // open: nothing calculated yet

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.payslips'))
            ->assertInertia(fn (Assert $page) => $page->where('period', null));

        $this->actingAs(User::factory()->admin()->create())->get(route('super-admin.payslips'))->assertForbidden();
    }
}
