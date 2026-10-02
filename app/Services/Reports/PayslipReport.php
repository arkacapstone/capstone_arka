<?php

namespace App\Services\Reports;

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use Illuminate\Support\Collection;

/**
 * Released payslips (one per contractor per period) for the periods that overlap the report dates.
 */
class PayslipReport implements Report
{
    public function key(): string
    {
        return 'payslip';
    }

    public function title(): string
    {
        return 'Payslip Report';
    }

    public function summaryColumns(): array
    {
        return ['Period', 'Release date', 'Payslips', 'Gross pay', 'Net pay'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return $this->released($filters)
            ->groupBy(fn (Payroll $row) => $row->period_id)
            ->map(fn ($rows) => [
                $rows->first()->period->period_name,
                $rows->first()->period->release_date?->toDateString(),
                $rows->pluck('employee_id')->unique()->count(),
                round($rows->sum(fn (Payroll $row) => (float) $row->gross_pay), 2),
                round($rows->sum(fn (Payroll $row) => (float) $row->net_pay), 2),
            ])
            ->sortBy(1)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Period', 'Clients', 'Gross pay', 'Deductions', 'Net pay'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        // A payslip combines every client row the contractor had in the period.
        return $this->released($filters)
            ->groupBy(fn (Payroll $row) => "{$row->period_id}-{$row->employee_id}")
            ->map(fn ($rows) => [
                $rows->first()->employee->name,
                $rows->first()->period->period_name,
                $rows->count(),
                round($rows->sum(fn (Payroll $row) => (float) $row->gross_pay), 2),
                round($rows->sum(fn (Payroll $row) => PayrollReport::deductions($row)), 2),
                round($rows->sum(fn (Payroll $row) => (float) $row->net_pay), 2),
            ])
            ->sortBy([[1, 'asc'], [0, 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Payroll>
     */
    private function released(ReportFilters $filters): Collection
    {
        return PayrollReport::rows(new ReportFilters($filters->from, $filters->to, $filters->employeeId))
            ->where('status', PayrollStatus::Released)
            ->with(['employee:id,name', 'period:id,period_name,release_date'])
            ->get();
    }
}
