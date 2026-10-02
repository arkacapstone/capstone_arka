<?php

namespace App\Http\Middleware;

use App\Support\ViewMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opening an Admin page puts an Admin in the Admin view, and opening a Contractor page puts
 * them in their Contractor view, so the sidebar always matches the page, e.g. `view:employee`.
 */
class RememberViewMode
{
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        ViewMode::remember($request, $mode);

        return $next($request);
    }
}
