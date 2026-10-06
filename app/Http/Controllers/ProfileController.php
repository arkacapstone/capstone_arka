<?php

namespace App\Http\Controllers;

use App\Enums\Appearance;
use App\Http\Middleware\RequireSecurityConfirmation;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

// use Laravel\Passkeys\Passkey;

/**
 * Profile & Security (Blueprint §3.4). Accounts are never self-deleted: they are deactivated
 * by an administrator so attendance, payroll and approval history stay intact.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return $this->settings($request, 'profile');
    }

    /**
     * Settings → Security: change password and manage passkeys.
     */
    public function security(Request $request): Response
    {
        return $this->settings($request, 'security');
    }

    /**
     * Opening Settings → Security always asks for the password first.
     */
    public function confirmSecurity(Request $request): Response
    {
        return $this->settings($request, 'confirm');
    }

    public function unlockSecurity(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'That password is incorrect.',
        ]);

        $request->session()->put(RequireSecurityConfirmation::SESSION_KEY, now()->timestamp);
        // Also counts as a password confirmation, so passkeys can be managed without asking again.
        $request->session()->put('auth.password_confirmed_at', time());

        return to_route('profile.security');
    }

    /**
     * Settings → Appearance: Light, Dark or System.
     */
    public function appearanceSettings(Request $request): Response
    {
        return $this->settings($request, 'appearance');
    }

    private function settings(Request $request, string $section): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Profile', [
            'section' => $section,
            'passkeys' => $section === 'security'
                ? $user->passkeys()->latest()->get()->map(fn (Passkey $passkey) => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'createdAt' => $passkey->created_at?->toIso8601String(),
                    'lastUsedAt' => $passkey->last_used_at?->toIso8601String(),
                ])->all()
                : [],
            'account' => [
                'employeeCode' => $user->employee_code,
                'role' => $user->role->label(),
                'status' => ucfirst($user->status),
                'phoneNumber' => $user->phone_number,
                'birthday' => $user->birthday?->toDateString(),
                'age' => $user->birthday?->age,
                'address' => $user->address,
                'emergencyContactName' => $user->emergency_contact_name,
                'emergencyContactNumber' => $user->emergency_contact_number,
                'memberSince' => $user->created_at?->toDateString(),
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                'canEditEmail' => $user->isSuperAdmin(),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->allowedChanges());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile saved.');
    }

    /**
     * Saves the Light / Dark / System appearance so it follows the user to every device.
     */
    public function appearance(Request $request): RedirectResponse
    {
        $validated = $request->validate(['appearance' => ['required', Rule::enum(Appearance::class)]]);

        $request->user()->update(['appearance' => $validated['appearance']]);

        return back();
    }
}
