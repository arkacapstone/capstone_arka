<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use App\Support\NotificationFeed;
use App\Support\ViewMode;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'role' => $user->role?->value,
                    'roleLabel' => $user->role?->label(),
                    'mustChangePassword' => $user->must_change_password,
                    'appearance' => $user->appearance?->value ?? 'system',
                ] : null,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'invitation' => $request->session()->get('invitation'),
            ],
            // Lazy: route middleware (e.g. `view:employee`) settles the view after this runs.
            'navigation' => fn () => Navigation::for($user, ViewMode::current($request)),
            'viewMode' => fn () => ViewMode::current($request),
            'canSwitchView' => ViewMode::canSwitch($user),
            'notifications' => fn () => $user ? NotificationFeed::for($user) : null,
        ];
    }
}
