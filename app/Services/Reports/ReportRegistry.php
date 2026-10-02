<?php

namespace App\Services\Reports;

/**
 * The reports on each side. Admins get attendance, leave and devotional reports (Admin flow §VII);
 * payroll-related reports stay on the Super Admin side, which also sees every Admin report.
 */
class ReportRegistry
{
    public function __construct(
        private readonly AttendanceReport $attendance,
        private readonly LeaveReport $leave,
        private readonly DevotionalReport $devotional,
        private readonly WorkforceReport $workforce,
        private readonly ScheduleReport $schedule,
        private readonly PayrollReport $payroll,
        private readonly PayslipReport $payslip,
        private readonly DeductionReport $deduction,
        private readonly CashAdvanceReport $cashAdvance,
        private readonly PerformanceReport $performance,
    ) {}

    /**
     * @return array<string, Report>
     */
    public function all(): array
    {
        return self::keyed([$this->attendance, $this->leave, $this->devotional]);
    }

    /**
     * @return array<string, Report>
     */
    public function forSuperAdmin(): array
    {
        return self::keyed([
            $this->attendance, $this->devotional, $this->leave, $this->workforce, $this->schedule,
            $this->payroll, $this->payslip, $this->deduction, $this->cashAdvance, $this->performance,
        ]);
    }

    public function find(string $key, bool $superAdmin = false): Report
    {
        return ($superAdmin ? $this->forSuperAdmin() : $this->all())[$key] ?? abort(404);
    }

    /**
     * @param  list<Report>  $reports
     * @return array<string, Report>
     */
    private static function keyed(array $reports): array
    {
        return collect($reports)->keyBy(fn (Report $report) => $report->key())->all();
    }
}
