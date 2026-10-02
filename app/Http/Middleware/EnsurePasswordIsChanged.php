<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users with a temporary password to the Set New Password screen before anything else loads
 * (Admin flow §I, Blueprint §18 step 3).
 */
class EnsurePasswordIsChanged
{
    /**
     * Routes that stay reachable while a password change is pending.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'password.setup',
        'password.setup.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('password.setup');
        }

        return $next($request);
    }
}
