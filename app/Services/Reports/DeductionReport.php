<?php

namespace App\Services\Reports;

use App\Models\Payroll;
use Illuminate\Support\Collection;

/**
 * Approved payroll deductions by type. Tithes and devotional penalties never appear: they are
 * not payroll deductions (Blueprint §9).
 */
class DeductionReport implements Report
{
    private const TYPES = [
        'absence_deduction' => 'Absences',
        'late_deduction' => 'Late / undertime',
        'cash_advance_deduction' => 'Cash advance',
        'device_deduction' => 'Device loss / damage',
        'other_deductions' => 'Other',
    ];

    public function key(): string
    {
        return 'deduction';
    }

    public function title(): string
    {
        return 'Deduction Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', ...array_values(self::TYPES), 'Total'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return $this->rows($filters)
            ->groupBy('employee_id')
            ->map(function ($rows) {
                $amounts = array_map(fn (string $column) => round($rows->sum(fn (Payroll $row) => (float) $row->{$column}), 2), array_keys(self::TYPES));

                return [$rows->first()->employee->name, ...$amounts, round(array_sum($amounts), 2)];
            })
            ->filter(fn (array $row) => end($row) > 0)
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Period', 'Client', 'Deduction', 'Amount'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return $this->rows($filters)
            ->flatMap(fn (Payroll $row) => collect(self::TYPES)
                ->filter(fn (string $label, string $column) => (float) $row->{$column} > 0)
                ->map(fn (string $label, string $column) => [
                    $row->employee->name,
                    $row->period->period_name,
                    $row->client?->client_name,
                    $label,
                    (float) $row->{$column},
                ])
                ->values())
            ->sortBy([[1, 'asc'], [0, 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Payroll>
     */
    private function rows(ReportFilters $filters): Collection
    {
        return PayrollReport::rows(new ReportFilters($filters->from, $filters->to, $filters->employeeId))
            ->with(['employee:id,name', 'period:id,period_name', 'client:id,client_name'])
            ->get();
    }
}
