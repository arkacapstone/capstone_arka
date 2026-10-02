<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\PayrollPeriodStatus;
use App\Services\Dashboard\Contracts\DashboardWidget;

/**
 * Turns the other widgets' numbers into short, actionable alerts.
 * Alerts are informational only; they never replace the underlying records (Blueprint §16).
 */
class AlertsWidget implements DashboardWidget
{
    /**
     * @param  array<string, mixed>  $payroll
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $pendingApprovals
     */
    public function __construct(
        private readonly array $payroll,
        private readonly array $attendance,
        private readonly array $pendingApprovals,
    ) {}

    public function key(): string
    {
        return 'alerts';
    }

    /**
     * @return list<array{level: 'info'|'warning'|'danger', title: string, message: string, href: ?string}>
     */
    public function data(): array
    {
        $alerts = [];
        $period = $this->payroll['period'];
        $daysUntilRelease = $this->payroll['daysUntilRelease'];

        if ($period['isProjected']) {
            $alerts[] = $this->alert('info', 'Payroll period not created', 'Showing the default semi-monthly cycle. Create the period to start processing.', route('super-admin.payroll'));
        }

        if ($period['status'] === PayrollPeriodStatus::Verification->value) {
            $alerts[] = $this->alert('warning', 'Attendance verification in progress', 'Contractors are confirming attendance. It locks automatically after the deadline.', null);
        }

        if ($period['status'] !== PayrollPeriodStatus::Released->value && $daysUntilRelease >= 0 && $daysUntilRelease <= 2) {
            $alerts[] = $this->alert('warning', 'Salary release is near', $daysUntilRelease === 0 ? 'Payroll is due for release today.' : "Payroll is due for release in {$daysUntilRelease} day(s).", route('super-admin.payroll'));
        }

        if ($this->attendance['missingClockOut'] > 0) {
            $alerts[] = $this->alert('danger', 'Missing clock-outs', "{$this->attendance['missingClockOut']} timer(s) were never stopped. Those days default to Absent until resolved.", null);
        }

        if ($this->attendance['correctionsPending'] > 0) {
            $alerts[] = $this->alert('info', 'Attendance corrections pending', "{$this->attendance['correctionsPending']} correction request(s) are waiting for Admin review.", null);
        }

        if ($this->pendingApprovals['total'] > 0) {
            $alerts[] = $this->alert('warning', 'Approvals waiting', "{$this->pendingApprovals['total']} item(s) need your decision.", route('super-admin.requests'));
        }

        return $alerts;
    }

    /**
     * @param  'info'|'warning'|'danger'  $level
     * @return array{level: 'info'|'warning'|'danger', title: string, message: string, href: ?string}
     */
    private function alert(string $level, string $title, string $message, ?string $href): array
    {
        return compact('level', 'title', 'message', 'href');
    }
}
