<?php

namespace App\Http\Controllers\SuperAdmin\Workforce;

use App\Actions\Workforce\ChangeRate;
use App\Actions\Workforce\EndClientAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RateRequest;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Rates of approved client assignments. Rates are money, so only the Super Admin sets them (Blueprint §2, §21).
 * New assignments come from Admins and are approved in Requests & Approvals.
 */
class EmployeeRateController extends Controller
{
    public function update(RateRequest $request, User $account, Rate $rate, ChangeRate $changeRate): RedirectResponse
    {
        $changeRate->handle($this->rateOf($account, $rate), $request->validated());

        return back()->with('success', 'Rate updated. The previous rate is kept in history.');
    }

    public function destroy(User $account, Rate $rate, EndClientAssignment $endAssignment): RedirectResponse
    {
        $endAssignment->handle($this->rateOf($account, $rate));

        return back()->with('success', 'Client assignment ended.');
    }

    private function employee(User $account): User
    {
        abort_unless($account->hasEmployeePortal(), 404);

        return $account;
    }

    private function rateOf(User $account, Rate $rate): Rate
    {
        abort_unless($rate->employee_id === $this->employee($account)->id, 404);

        return $rate;
    }
}
