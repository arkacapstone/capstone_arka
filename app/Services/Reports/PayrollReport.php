<?php

namespace App\Services\Reports;

use App\Models\Payroll;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payroll per contractor for the pay periods that overlap the report dates. Super Admin only.
 */
class PayrollReport implements Report
{
    public function key(): string
    {
        return 'payroll';
    }

    public function title(): string
    {
        return 'Payroll Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Payroll rows', 'Gross pay', 'Additions', 'Deductions', 'Net pay'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return self::rows($filters)
            ->with('employee:id,name')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => [
                $rows->first()->employee->name,
                $rows->count(),
                round($rows->sum(fn (Payroll $row) => (float) $row->gross_pay), 2),
                round($rows->sum(fn (Payroll $row) => self::additions($row)), 2),
                round($rows->sum(fn (Payroll $row) => self::deductions($row)), 2),
                round($rows->sum(fn (Payroll $row) => (float) $row->net_pay), 2),
            ])
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Period', 'Client', 'Gross pay', 'Additions', 'Deductions', 'Net pay', 'Status'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return self::rows($filters)
            ->with(['employee:id,name', 'period:id,period_name,start_date', 'client:id,client_name'])
            ->get()
            ->sortBy([fn (Payroll $a, Payroll $b) => $a->period->start_date <=> $b->period->start_date, fn (Payroll $a, Payroll $b) => $a->employee->name <=> $b->employee->name])
            ->map(fn (Payroll $row) => [
                $row->employee->name,
                $row->period->period_name,
                $row->client?->client_name,
                (float) $row->gross_pay,
                self::additions($row),
                self::deductions($row),
                (float) $row->net_pay,
                ucfirst($row->status->value),
            ])
            ->values()
            ->all();
    }

    /**
     * Payroll rows in periods that overlap the report dates.
     *
     * @return Builder<Payroll>
     */
    public static function rows(ReportFilters $filters): Builder
    {
        return Payroll::query()
            ->whereHas('period', fn (Builder $query) => $query
                ->whereDate('start_date', '<=', $filters->to)
                ->whereDate('end_date', '>=', $filters->from))
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    public static function additions(Payroll $row): float
    {
        return round((float) $row->additional_pay + (float) $row->overtime_amount + (float) $row->reward_amount, 2);
    }

    public static function deductions(Payroll $row): float
    {
        return round(
            (float) $row->absence_deduction + (float) $row->late_deduction + (float) $row->cash_advance_deduction
            + (float) $row->device_deduction + (float) $row->other_deductions,
            2,
        );
    }
}
