<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forced "Set New Password" step on first login with a temporary password (Admin flow §I, Blueprint §18 step 3).
 */
class PasswordSetupController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('Auth/SetPassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        if (Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => 'Choose a password different from your default one.']);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        if ($user->needsProfileSetup()) {
            return to_route('profile.setup')->with('success', 'Password set. Now complete your profile.');
        }

        return to_route('dashboard')->with('success', 'Password set. Welcome to ARKA.');
    }
}
