<?php

namespace App\Services\Reports;

use App\Models\CashAdvance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cash advances requested in the period, what was released and repaid, and the balance left.
 */
class CashAdvanceReport implements Report
{
    public function key(): string
    {
        return 'cash-advance';
    }

    public function title(): string
    {
        return 'Cash Advance Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Requests', 'Released', 'Repaid', 'Remaining balance'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with('employee:id,name')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($advances) {
                $released = $advances->filter(fn (CashAdvance $advance) => $advance->released_date !== null);

                return [
                    $advances->first()->employee->name,
                    $advances->count(),
                    round($released->sum(fn (CashAdvance $advance) => (float) $advance->amount), 2),
                    round($released->sum(fn (CashAdvance $advance) => (float) $advance->amount - (float) $advance->remaining_balance), 2),
                    round($released->sum(fn (CashAdvance $advance) => (float) $advance->remaining_balance), 2),
                ];
            })
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Requested', 'Amount', 'Released', 'Repaid', 'Remaining', 'Status', 'Reason'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with('employee:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CashAdvance $advance) => [
                $advance->employee->name,
                $advance->created_at->toDateString(),
                (float) $advance->amount,
                $advance->released_date?->toDateString(),
                $advance->released_date ? round((float) $advance->amount - (float) $advance->remaining_balance, 2) : null,
                $advance->released_date ? (float) $advance->remaining_balance : null,
                $advance->status->label(),
                $advance->reason,
            ])
            ->all();
    }

    /**
     * @return Builder<CashAdvance>
     */
    private function query(ReportFilters $filters): Builder
    {
        return CashAdvance::query()
            ->whereDate('created_at', '>=', $filters->from)
            ->whereDate('created_at', '<=', $filters->to)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
