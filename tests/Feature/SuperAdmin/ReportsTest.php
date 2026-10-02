<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\CashAdvance;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 10:00:00');
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_the_super_admin_sees_the_payroll_related_reports_and_admins_do_not(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->has('reports', 10)
                ->where('routes.show', 'super-admin.reports.show'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', 'payroll'))
            ->assertNotFound();
    }

    public function test_every_report_generates(): void
    {
        foreach (['attendance', 'devotional', 'leave', 'workforce', 'schedule', 'payroll', 'payslip', 'deduction', 'cash-advance', 'performance'] as $report) {
            $this->actingAs($this->superAdmin)
                ->get(route('super-admin.reports.show', $report))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Admin/Reports/Show')->where('report.key', $report));
        }
    }

    public function test_the_payroll_report_totals_each_employees_pay(): void
    {
        $period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Released]);
        $employee = User::factory()->create(['name' => 'Christian Mae']);

        Payroll::factory()->for($employee, 'employee')->for($period, 'period')->create([
            'gross_pay' => 10000, 'additional_pay' => 500, 'overtime_amount' => 0,
            'absence_deduction' => 0, 'late_deduction' => 200, 'cash_advance_deduction' => 1000,
            'device_deduction' => 0, 'other_deductions' => 0, 'net_pay' => 9300,
            'status' => PayrollStatus::Released,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.reports.show', ['report' => 'payroll', 'from' => '2026-09-11', 'to' => '2026-09-25']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.rows.0', ['Christian Mae', 1, 10000, 500, 1200, 9300]));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.reports.show', ['report' => 'deduction', 'from' => '2026-09-11', 'to' => '2026-09-25']))
            ->assertInertia(fn (Assert $page) => $page->has('details.rows', 2)->where('summary.rows.0.6', 1200));
    }

    public function test_the_cash_advance_report_exports_as_csv(): void
    {
        CashAdvance::factory()->create(['amount' => 3000]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.reports.show', ['report' => 'cash-advance', 'export' => 'csv']));

        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('Cash Advance Report', $response->streamedContent());
    }
}
