<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\CashAdvances\ManageCashAdvance;
use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Super Admin decisions on cash advances (Blueprint §13), listed under Requests & Approvals.
 * Contractors file the request and amount; the Super Admin approves (for that amount or less) or
 * rejects. Repayment comes out of that payday's payroll automatically.
 */
class CashAdvanceController extends Controller
{
    /**
     * The old Cash Advances page now lives under Requests & Approvals; links to it still work.
     */
    public function index(Request $request): RedirectResponse
    {
        return to_route('super-admin.requests', ['type' => 'cash-advances', ...$request->only('tab', 'search')]);
    }

    /**
     * Released today, for the requested amount or less. Repayment is deducted from that payday's payroll automatically.
     */
    public function approve(Request $request, CashAdvance $advance, ManageCashAdvance $manage): RedirectResponse
    {
        $validated = $request->validate(['amount' => ['nullable', 'numeric', 'min:1', 'max:10000000']]);

        $manage->approve($request->user(), $advance, CarbonImmutable::today(), isset($validated['amount']) ? (float) $validated['amount'] : null);

        return back()->with('success', "Cash advance approved. Repayment will be deducted from the contractor's payroll.");
    }

    public function reject(Request $request, CashAdvance $advance, ManageCashAdvance $manage): RedirectResponse
    {
        $manage->reject($request->user(), $advance);

        return back()->with('success', 'Cash advance request closed. The contractor has been notified.');
    }
}
