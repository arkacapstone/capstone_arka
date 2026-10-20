<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if ($request->input('portal') === 'super_admin' && ! $request->user()->isSuperAdmin()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'This log in is for Super Admins. Use "Log in as Admin / Contractor" instead.',
            ]);
        }

        $request->session()->regenerate();

        // The page saved before login may belong to another role (e.g. a Super Admin page left
        // open, then an Admin signs in); send them to their own dashboard instead of a 403.
        $intended = $request->session()->pull('url.intended');

        return redirect()->to($intended && $this->canOpen($request, $intended) ? $intended : route('dashboard', absolute: false));
    }

    /**
     * Whether the signed-in user's role passes the `role:` middleware of the page at this URL.
     */
    private function canOpen(Request $request, string $url): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($url));
        } catch (\Throwable) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                $roles = array_map(UserRole::from(...), explode(',', substr($middleware, 5)));

                if (! $request->user()->hasRole(...$roles)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
