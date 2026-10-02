<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the first password change, sends new Admins and Contractors to Complete your profile
 * so they fill in their own personal details before anything else loads.
 */
class EnsureProfileIsCompleted
{
    /**
     * Routes that stay reachable while the profile is incomplete.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'password.setup',
        'password.setup.update',
        'profile.setup',
        'profile.setup.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsProfileSetup() && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('profile.setup');
        }

        return $next($request);
    }
}
