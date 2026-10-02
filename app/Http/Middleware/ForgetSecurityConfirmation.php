<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks Settings → Security again as soon as the user opens any other page, so every visit to
 * Security asks for the password. Background JSON calls (e.g. the notification bell) don't count.
 */
class ForgetSecurityConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && $request->hasSession()
            && ! $request->expectsJson()
            && ! $request->routeIs('profile.security', 'profile.security.confirm')) {
            $request->session()->forget(RequireSecurityConfirmation::SESSION_KEY);
        }

        return $next($request);
    }
}
