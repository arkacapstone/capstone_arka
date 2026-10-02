<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the given roles, e.g. `role:primary_admin`.
 * Keeps money-related modules out of reach of Admins and Contractors (Blueprint §21).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless($request->user()?->hasRole(...$allowed), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
