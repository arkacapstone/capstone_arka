<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\CashAdvances\ManageCashAdvance;
use App\Enums\CashAdvanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRepayment;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Cash Advances (Blueprint §3.1 module 6, §13). Contractors file the request and
 * amount; the Super Admin only approves or rejects. Repayment comes out of payroll automatically.
 */
class CashAdvanceController extends Controller
{
    public function index(Request $request, SystemRules $rules): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:pending,active,history'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $tab = $filters['tab'] ?? 'pending';

        $advances = CashAdvance::query()
            ->with(['employee:id,name,employee_code', 'approver:id,name', 'repayments' => fn ($query) => $query->latest('repayment_date')])
            ->when($tab === 'pending', fn (Builder $query) => $query->where('status', CashAdvanceStatus::Pending))
            ->when($tab === 'active', fn (Builder $query) => $query->outstanding())
            ->when($tab === 'history', fn (Builder $query) => $query->whereIn('status', [CashAdvanceStatus::Repaid, CashAdvanceStatus::Rejected, CashAdvanceStatus::Cancelled]))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
                'employee', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%")
            ))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (CashAdvance $advance) => [
                'id' => $advance->id,
                'employee' => ['name' => $advance->employee->name, 'code' => $advance->employee->employee_code],
                'amount' => (float) $advance->amount,
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
            ]);

        return Inertia::render('SuperAdmin/CashAdvances/Index', [
            'tab' => $tab,
            'advances' => $advances->all(),
            'filters' => ['tab' => $tab, 'search' => $filters['search'] ?? ''],
            'summary' => [
                'pending' => CashAdvance::query()->where('status', CashAdvanceStatus::Pending)->count(),
                'outstanding' => round((float) CashAdvance::query()->outstanding()->sum('remaining_balance'), 2),
                'activeCount' => CashAdvance::query()->outstanding()->count(),
                'releasedThisMonth' => round((float) CashAdvance::query()->whereDate('released_date', '>=', CarbonImmutable::today()->startOfMonth())->sum('amount'), 2),
            ],
            'rules' => [
                'maxAmount' => $rules->decimal('cash_advance_max_amount'),
            ],
        ]);
    }

    /**
     * Released today. Repayment is deducted from the contractor's payroll automatically.
     */
    public function approve(Request $request, CashAdvance $advance, ManageCashAdvance $manage): RedirectResponse
    {
        $manage->approve($request->user(), $advance, CarbonImmutable::today());

        return back()->with('success', "Cash advance approved. Repayment will be deducted from the contractor's payroll.");
    }

    public function reject(Request $request, CashAdvance $advance, ManageCashAdvance $manage): RedirectResponse
    {
        $manage->reject($request->user(), $advance);

        return back()->with('success', 'Cash advance request closed. The contractor has been notified.');
    }
}
