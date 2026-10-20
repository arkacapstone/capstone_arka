<?php

namespace App\Http\Controllers\Employee;

use App\Actions\CashAdvances\ManageCashAdvance;
use App\Enums\CashAdvanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Cash Advance: their own requests (Blueprint §15: submitted by the contractor,
 * decided by the Super Admin), with the balance still being repaid through payroll (Blueprint §13).
 */
class CashAdvanceController extends Controller
{
    public function index(Request $request, ManageCashAdvance $manage): Response
    {
        $window = $manage->window($request->user());

        return Inertia::render('Employee/CashAdvances', [
            'advances' => $request->user()->cashAdvances()->withBalance()->latest()->limit(20)->get()->map(fn (CashAdvance $advance) => [
                'id' => $advance->id,
                'amount' => (float) $advance->amount,
                'requestedAmount' => (float) ($advance->requested_amount ?? $advance->amount),
                'payday' => $advance->payday?->toDateString(),
                'remaining' => (float) $advance->remaining_balance,
                'repaymentMonths' => $advance->repayment_months,
                'installment' => $advance->monthly_installment,
                'reason' => $advance->reason,
                'status' => $advance->displayStatus()->value,
                'statusLabel' => $advance->displayStatus()->label(),
                'requestedAt' => $advance->created_at->toIso8601String(),
                'releasedDate' => $advance->released_date?->toDateString(),
                'canCancel' => $advance->status === CashAdvanceStatus::Pending,
            ])->all(),
            'requestWindow' => [
                'open' => $window['open'],
                'reason' => $window['reason'],
                'limit' => $window['limit'],
                'payday' => $window['period']?->release_date->toDateString(),
            ],
        ]);
    }

    public function store(Request $request, ManageCashAdvance $manage): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'reason' => ['required', 'string', 'max:500'],
            'repayment_months' => ['required', 'integer', Rule::in(CashAdvance::REPAYMENT_MONTHS)],
        ], ['repayment_months.in' => 'Choose 2 or 3 months to repay it.']);

        $manage->request($request->user(), (float) $validated['amount'], $validated['reason'], (int) $validated['repayment_months']);

        return back()->with('success', 'Cash advance request sent to the Super Admin.');
    }

    public function cancel(Request $request, CashAdvance $advance, ManageCashAdvance $manage): RedirectResponse
    {
        abort_unless($advance->employee_id === $request->user()->id, 404);

        $manage->cancel($advance);

        return back()->with('success', 'Cash advance request withdrawn.');
    }
}
