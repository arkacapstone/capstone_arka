<?php

namespace App\Http\Controllers\Employee;

use App\Actions\CashAdvances\ManageCashAdvance;
use App\Enums\CashAdvanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Services\Settings\SystemRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Cash Advance: their own requests (Blueprint §15: submitted by the contractor,
 * decided by the Super Admin), with the balance still being repaid through payroll (Blueprint §13).
 */
class CashAdvanceController extends Controller
{
    public function index(Request $request, SystemRules $rules): Response
    {
        return Inertia::render('Employee/CashAdvances', [
            'advances' => $request->user()->cashAdvances()->latest()->limit(20)->get()->map(fn (CashAdvance $advance) => [
                'id' => $advance->id,
                'amount' => (float) $advance->amount,
                'remaining' => (float) $advance->remaining_balance,
                'reason' => $advance->reason,
                'status' => $advance->status->value,
                'statusLabel' => $advance->status->label(),
                'requestedAt' => $advance->created_at->toIso8601String(),
                'releasedDate' => $advance->released_date?->toDateString(),
                'canCancel' => $advance->status === CashAdvanceStatus::Pending,
            ])->all(),
            'rules' => [
                'maxAmount' => $rules->decimal('cash_advance_max_amount'),
            ],
        ]);
    }

    public function store(Request $request, ManageCashAdvance $manage): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $manage->request($request->user(), (float) $validated['amount'], $validated['reason']);

        return back()->with('success', 'Cash advance request sent to the Super Admin.');
    }

    public function cancel(Request $request, CashAdvance $advance, ManageCashAdvance $manage): RedirectResponse
    {
        abort_unless($advance->employee_id === $request->user()->id, 404);

        $manage->cancel($advance);

        return back()->with('success', 'Cash advance request withdrawn.');
    }
}
