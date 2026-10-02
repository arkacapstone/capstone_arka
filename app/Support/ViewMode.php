<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Which dashboard the signed-in user is looking at (Admin flow §X).
 *
 * An Admin is also a employee: the same session moves between the Admin view and
 * their own Contractor view without signing in again. Contractors only have the Contractor
 * view and the Super Admin only has their own console.
 */
final class ViewMode
{
    public const ADMIN = 'admin';

    public const EMPLOYEE = 'employee';

    public const SUPER_ADMIN = 'super_admin';

    private const SESSION_KEY = 'view_mode';

    public static function current(Request $request): ?string
    {
        $user = $request->user();

        return match ($user?->role) {
            UserRole::SuperAdmin => self::SUPER_ADMIN,
            UserRole::Employee => self::EMPLOYEE,
            UserRole::Admin => $request->hasSession() && $request->session()->get(self::SESSION_KEY) === self::EMPLOYEE
                ? self::EMPLOYEE
                : self::ADMIN,
            default => null,
        };
    }

    public static function canSwitch(?User $user): bool
    {
        return $user?->isAdmin() ?? false;
    }

    public static function remember(Request $request, string $mode): void
    {
        if (self::canSwitch($request->user()) && in_array($mode, [self::ADMIN, self::EMPLOYEE], true)) {
            $request->session()->put(self::SESSION_KEY, $mode);
        }
    }

    /**
     * The home route for the user's current view.
     */
    public static function homeRoute(Request $request): string
    {
        return match (self::current($request)) {
            self::SUPER_ADMIN => 'super-admin.dashboard',
            self::ADMIN => 'admin.dashboard',
            default => 'employee.dashboard',
        };
    }
}
