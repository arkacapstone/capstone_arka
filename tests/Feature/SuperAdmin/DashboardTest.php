<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Client;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 10:00:00');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('super-admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admins_and_employees_cannot_open_the_super_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('super-admin.payroll'))
            ->assertForbidden();
    }

    public function test_super_admin_is_sent_to_their_dashboard_after_login(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_dashboard_projects_the_current_semi_monthly_period_when_none_exists(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Dashboard')
                ->where('payroll.period.startDate', '2026-09-11')
                ->where('payroll.period.releaseDate', '2026-09-30')
                ->where('payroll.period.status', 'verification')
                ->where('payroll.period.isProjected', true)
                ->where('payroll.daysUntilRelease', 5)
                // Nothing is saved yet, so the Super Admin still has to open verification (step 1).
                ->where('payroll.steps.0.state', 'current')
                ->where('payroll.action.label', 'Open attendance verification')
                ->has('navigation', 11)
                ->where('navigation.1.key', 'workforce')
                ->where('navigation.9.key', 'notifications')
            );
    }

    public function test_payroll_totals_follow_the_blueprint_formula(): void
    {
        $period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Locked]);
        Payroll::factory()->for($period, 'period')->create();
        Payroll::factory()->for($period, 'period')->reviewed()->create(['overtime_amount' => 500, 'cash_advance_deduction' => 1000, 'net_pay' => 18875]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.period.isProjected', false)
                ->where('payroll.period.status', 'locked')
                ->where('payroll.totals.gross', 40000)
                ->where('payroll.totals.additions', 1750)
                ->where('payroll.totals.deductions', 3500)
                ->where('payroll.totals.net', 38250)
                ->where('payroll.records.total', 2)
                ->where('payroll.records.byStatus.reviewed', 1)
                ->where('pendingApprovals.items.2.count', 1)
            );
    }

    public function test_pending_approvals_are_counted(): void
    {
        LeaveRequest::factory()->count(2)->create();
        LeaveRequest::factory()->approved()->create();
        CashAdvance::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingApprovals.total', 3)
                ->where('pendingApprovals.items.0.count', 2)
                ->where('pendingApprovals.items.1.count', 1)
            );
    }

    public function test_attendance_summary_reflects_todays_records(): void
    {
        $employees = User::factory()->count(5)->create();
        $client = Client::factory()->create();

        Attendance::factory()->for($employees[0], 'employee')->for($client)->status(AttendanceStatus::Present)->create();
        Attendance::factory()->for($employees[1], 'employee')->for($client)->status(AttendanceStatus::Late)->create();
        Attendance::factory()->for($employees[2], 'employee')->for($client)->status(AttendanceStatus::PaidLeave)->create();
        Attendance::factory()->for($employees[3], 'employee')->for($client)->status(AttendanceStatus::Absent)->create(['date' => '2026-09-24']);

        TimeLog::factory()->for($employees[0], 'employee')->for($client)->create();
        TimeLog::factory()->for($employees[4], 'employee')->for($client)->forgotten()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.expected', 5)
                ->where('attendance.accounted', 3)
                ->where('attendance.breakdown.0.count', 1)
                ->where('attendance.breakdown.1.count', 1)
                ->where('attendance.breakdown.3.count', 0)
                ->where('attendance.breakdown.4.count', 1)
                ->where('attendance.clockedIn', 1)
                ->where('attendance.missingClockOut', 1)
                ->where('alerts', fn ($alerts) => collect($alerts)->pluck('title')->contains('Missing clock-outs'))
            );
    }

    public function test_workforce_overview_splits_active_and_inactive_accounts(): void
    {
        User::factory()->count(5)->create();
        User::factory()->inactive()->create();
        User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();
        Client::factory()->count(3)->create();
        Client::factory()->inactive()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workforce.activeTotal', 6)
                ->where('workforce.employees', ['active' => 5, 'inactive' => 1])
                ->where('workforce.admins', ['active' => 1, 'inactive' => 1])
                ->where('workforce.activeClients', 3)
            );
    }

    public function test_super_admin_can_open_every_module_from_the_sidebar(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $pages = [
            'performance' => 'SuperAdmin/Performance/Index',
            'rules' => 'SuperAdmin/Rules/Index',
            'reports' => 'Admin/Reports/Index',
            'notifications' => 'SuperAdmin/Notifications/Index',
            'activity-logs' => 'SuperAdmin/ActivityLogs/Index',
        ];

        foreach ($pages as $module => $component) {
            $this->actingAs($superAdmin)
                ->get(route("super-admin.{$module}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($superAdmin)
            ->get(route('super-admin.requests'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Requests/Index'));
    }
}
