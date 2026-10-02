<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureProfileIsCompleted;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForgetSecurityConfirmation;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RememberViewMode;
use App\Http\Middleware\RequireSecurityConfirmation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            EnsurePasswordIsChanged::class,
            EnsureProfileIsCompleted::class,
            ForgetSecurityConfirmation::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'view' => RememberViewMode::class,
            'security.confirm' => RequireSecurityConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
