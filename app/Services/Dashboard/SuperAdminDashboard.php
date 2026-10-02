<?php

namespace App\Services\Dashboard;

use App\Services\Dashboard\Contracts\DashboardWidget;
use App\Services\Dashboard\Widgets\AlertsWidget;
use App\Services\Dashboard\Widgets\AttendanceSummaryWidget;
use App\Services\Dashboard\Widgets\PayrollOverviewWidget;
use App\Services\Dashboard\Widgets\PendingApprovalsWidget;
use App\Services\Dashboard\Widgets\RecentActivityWidget;
use App\Services\Dashboard\Widgets\WorkforceOverviewWidget;
use App\Services\Payroll\PayrollPeriodResolver;
use Carbon\CarbonImmutable;

/**
 * Assembles the Super Admin dashboard (Blueprint §3.1 Dashboard): workforce overview,
 * payroll overview, pending approvals, attendance summary, and alerts.
 */
class SuperAdminDashboard
{
    public function __construct(private readonly PayrollPeriodResolver $periods) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        $data = $this->collect([
            new PayrollOverviewWidget($this->periods->current($today), $today),
            new PendingApprovalsWidget,
            new AttendanceSummaryWidget($today),
            new WorkforceOverviewWidget,
            new RecentActivityWidget,
        ]);

        return $data + $this->collect([
            new AlertsWidget($data['payroll'], $data['attendance'], $data['pendingApprovals']),
        ]);
    }

    /**
     * @param  list<DashboardWidget>  $widgets
     * @return array<string, mixed>
     */
    private function collect(array $widgets): array
    {
        $data = [];

        foreach ($widgets as $widget) {
            $data[$widget->key()] = $widget->data();
        }

        return $data;
    }
}
