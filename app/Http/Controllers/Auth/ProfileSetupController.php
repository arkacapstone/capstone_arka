<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ProfileSetupRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forced "Complete your profile" step right after the first password change, so the Admin
 * no longer types every contractor's personal details.
 */
class ProfileSetupController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->needsProfileSetup()) {
            return to_route('dashboard');
        }

        return Inertia::render('Auth/CompleteProfile', [
            'account' => [
                'employeeCode' => $user->employee_code,
                'role' => $user->role->label(),
                'phoneNumber' => $user->phone_number,
                'birthday' => $user->birthday?->toDateString(),
            ],
        ]);
    }

    public function update(ProfileSetupRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());
        $user->profile_completed_at = now();
        $user->save();

        $activity->log('workforce', "Completed {$user->role->label()} profile", $user, "{$user->name} ({$user->employee_code})");

        return to_route('dashboard')->with('success', 'Profile saved. Welcome to ARKA.');
    }
}
