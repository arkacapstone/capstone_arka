<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Notifications\PayslipIssueFlagged;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\Payslips\EmployeePayslips;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Payslip (Contractor flow §VIII): released payslips only, fully itemized.
 */
class PayslipController extends Controller
{
    public function index(Request $request, EmployeePayslips $payslips): Response
    {
        $user = $request->user();

        return Inertia::render('Employee/Payslips', [
            'payslips' => $payslips->for($user)->all(),
            'employee' => ['name' => $user->name, 'code' => $user->employee_code, 'type' => $user->employment_type?->label()],
        ]);
    }

    /**
     * "Flag an issue with this payslip" — sends the question to the Super Admin.
     */
    public function flag(Request $request, PayrollPeriod $period, Notifier $notifier, ActivityLogger $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->payrolls()->where('period_id', $period->id)->exists(), 404);

        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $notifier->superAdmins(new PayslipIssueFlagged($user, $period, $validated['note']));
        $activity->log('payslips', 'Flagged payslip issue', $period, "{$user->name}: {$validated['note']}");

        return back()->with('success', 'Sent to the Super Admin. You will hear back through notifications.');
    }
}
