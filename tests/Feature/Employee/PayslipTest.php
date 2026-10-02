<?php

namespace Tests\Feature\Employee;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Client;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Notifications\PayslipIssueFlagged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_released_payslip_is_itemized_per_client_without_government_deductions(): void
    {
        $employee = User::factory()->create();
        $period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Released]);

        foreach (['Northline' => 12000, 'Aurora Dental' => 25000] as $name => $gross) {
            Payroll::factory()->for($employee, 'employee')->for($period, 'period')->for(Client::factory()->create(['client_name' => $name]))->create(['status' => PayrollStatus::Released, 'gross_pay' => $gross]);
        }

        $this->actingAs($employee)
            ->get(route('employee.payslips.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Payslips')
                ->where('payslips.0.status', 'available')
                ->where('payslips.0.net', 38750)
                // The client that pays the most is Gross Pay; every other client is Additional Pay.
                ->where('payslips.0.earnings.0.label', fn (string $label) => str_starts_with($label, 'Gross Pay (Aurora Dental'))
                ->where('payslips.0.earnings.1.label', fn (string $label) => str_starts_with($label, 'Additional Pay (Northline'))
                ->where('payslips.0.deductions', fn ($lines) => collect($lines)->pluck('label')->doesntContain(fn ($label) => str_contains($label, 'SSS') || str_contains($label, 'tax')))
            );
    }

    public function test_an_unreleased_period_shows_as_processing_with_no_figures(): void
    {
        $employee = User::factory()->create();
        Payroll::factory()->for($employee, 'employee')->create();

        $this->actingAs($employee)
            ->get(route('employee.payslips.index'))
            ->assertInertia(fn (Assert $page) => $page->where('payslips.0.status', 'processing')->where('payslips.0.net', null));
    }

    public function test_flagging_an_issue_notifies_the_super_admin(): void
    {
        Notification::fake();

        $employee = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $payroll = Payroll::factory()->for($employee, 'employee')->create();

        $this->actingAs($employee)
            ->post(route('employee.payslips.flag', $payroll->period_id), ['note' => 'Hours look short.'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($superAdmin, PayslipIssueFlagged::class);

        $this->actingAs(User::factory()->create())
            ->post(route('employee.payslips.flag', $payroll->period_id), ['note' => 'x'])
            ->assertNotFound();
    }
}
