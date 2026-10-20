<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Accounts\ResetPassword;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot your password?" on the login page, for the Super Admin only: emails a new default password
 * they replace on their next login. Admins and contractors have their password reset by whoever
 * manages their account.
 */
class PasswordResetLinkController extends Controller
{
    public const SENT = 'If this is the Super Admin email, a new default password is on its way. Contractors and Admins: ask your Admin to reset your password.';

    /**
     * How long, in seconds, before the Super Admin's password can be reset this way again, so
     * someone who knows the address cannot keep replacing it.
     */
    private const COOLDOWN = 600;

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Every email gets the same answer, so this form never reveals which address belongs to the Super Admin.
     */
    public function store(Request $request, ResetPassword $resetPassword): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $superAdmin = User::query()
            ->where('email', $request->string('email'))
            ->withRole(UserRole::SuperAdmin)
            ->where('status', UserStatus::Active->value)
            ->first();

        $key = 'super-admin-password-reset:'.$superAdmin?->id;

        // Only a reset that went out starts the cooldown; a failed email can be retried right away.
        if ($superAdmin && ! RateLimiter::tooManyAttempts($key, 1) && $resetPassword->handleOrUndo($superAdmin)) {
            RateLimiter::hit($key, self::COOLDOWN);
        }

        return back()->with('status', self::SENT);
    }
}
