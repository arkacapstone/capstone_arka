<?php

namespace App\Http\Controllers;

use App\Support\ViewMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Switcher (Admin flow §X): an Admin moves between the Admin view and their own
 * Contractor view in the same session, with no re-login.
 */
class ViewModeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['mode' => ['required', 'in:'.ViewMode::ADMIN.','.ViewMode::EMPLOYEE]]);

        ViewMode::remember($request, $validated['mode']);

        return to_route(ViewMode::homeRoute($request));
    }
}
