<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\ReviewPeriodVerification;
use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Services\Dashboard\Widgets\VerificationResultsWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Period Verification. The Admin reviews the contractors' changes, reminds those who have
 * not submitted, and submits the verified period to the Super Admin.
 */
class PeriodVerificationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Verification/Index', [
            'verification' => (new VerificationResultsWidget)->data(),
        ]);
    }

    public function remind(Request $request, PayrollPeriod $period, ReviewPeriodVerification $review): RedirectResponse
    {
        $sent = $review->remind($request->user(), $period);

        return back()->with('success', $sent === 1 ? 'Reminder sent to 1 contractor.' : "Reminder sent to {$sent} contractors.");
    }

    public function submit(Request $request, PayrollPeriod $period, ReviewPeriodVerification $review): RedirectResponse
    {
        $review->submit($request->user(), $period);

        return back()->with('success', 'Verified period submitted. The Super Admin can now process payroll.');
    }
}
