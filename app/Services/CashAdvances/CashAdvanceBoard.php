<?php

namespace App\Services\CashAdvances;

use App\Enums\CashAdvanceStatus;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRepayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The cash advances shown to the Super Admin under Requests & Approvals (Blueprint §13): requests
 * waiting for a decision, advances being repaid, and closed ones, with the overall figures.
 */
class CashAdvanceBoard
{
    public const TABS = ['pending', 'active', 'history'];

    /**
     * @return list<array<string, mixed>>
     */
    public function advances(string $tab, ?string $search = null): array
    {
        return CashAdvance::query()
            ->with(['employee:id,name,employee_code', 'approver:id,name', 'repayments' => fn ($query) => $query->latest('repayment_date')])
            ->when($tab === 'pending', fn (Builder $query) => $query->where('status', CashAdvanceStatus::Pending))
            ->when($tab === 'active', fn (Builder $query) => $query->outstanding())
            ->when($tab === 'history', fn (Builder $query) => $query->whereIn('status', [CashAdvanceStatus::Repaid, CashAdvanceStatus::Rejected, CashAdvanceStatus::Cancelled]))
            ->when($search, fn (Builder $query, string $search) => $query->whereHas(
                'employee', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%")
            ))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (CashAdvance $advance) => [
                'id' => $advance->id,
                'employee' => ['name' => $advance->employee->name, 'code' => $advance->employee->employee_code],
                'amount' => (float) $advance->amount,
                'requestedAmount' => (float) ($advance->requested_amount ?? $advance->amount),
                'payday' => $advance->payday?->toDateString(),
                // The contractor's gross pay for that pay period: the most the request could be.
                'grossPay' => $advance->gross_pay !== null ? (float) $advance->gross_pay : null,
                'remaining' => (float) $advance->remaining_balance,
                'repaid' => round((float) $advance->amount - (float) $advance->remaining_balance, 2),
                'reason' => $advance->reason,
                'status' => $advance->status->value,
                'statusLabel' => $advance->status->label(),
                'requestedAt' => $advance->created_at->toIso8601String(),
                'releasedDate' => $advance->released_date?->toDateString(),
                'approver' => $advance->approver?->name,
                'repayments' => $advance->repayments->map(fn (CashAdvanceRepayment $repayment) => [
                    'id' => $repayment->id,
                    'amount' => (float) $repayment->amount,
                    'date' => $repayment->repayment_date->toDateString(),
                    'notes' => $repayment->notes,
                    'viaPayroll' => $repayment->payroll_id !== null,
                ])->all(),
            ])
            ->all();
    }

    /**
     * @return array{pending: int, outstanding: float, activeCount: int, releasedThisMonth: float}
     */
    public function summary(): array
    {
        return [
            'pending' => CashAdvance::query()->pending()->count(),
            'outstanding' => round((float) CashAdvance::query()->outstanding()->sum('remaining_balance'), 2),
            'activeCount' => CashAdvance::query()->outstanding()->count(),
            'releasedThisMonth' => round((float) CashAdvance::query()->whereDate('released_date', '>=', CarbonImmutable::today()->startOfMonth())->sum('amount'), 2),
        ];
    }
}
