<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → Security asks for the password every time it is opened. Confirming unlocks it
 * until the user moves to another page (see ForgetSecurityConfirmation) or for 15 minutes at most.
 */
class RequireSecurityConfirmation
{
    public const SESSION_KEY = 'security.unlocked_at';

    public const WINDOW_SECONDS = 900;

    public function handle(Request $request, Closure $next): Response
    {
        $unlockedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if (now()->timestamp - $unlockedAt > self::WINDOW_SECONDS) {
            $request->session()->forget(self::SESSION_KEY);

            return to_route('profile.security.confirm');
        }

        return $next($request);
    }
}
