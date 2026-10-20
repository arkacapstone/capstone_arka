<?php

namespace App\Providers;

use App\Services\Settings\MailSettings;
use App\Services\Settings\SystemRules;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Features;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request, so the rules are read from the database only once.
        $this->app->singleton(SystemRules::class);
        $this->app->singleton(MailSettings::class);

        // Email goes out through the Gmail account the Super Admin saved in System & Rules.
        // Read only when mail is actually used, not on every request.
        $this->app->afterResolving('mail.manager', function (MailManager $manager) {
            $this->app->make(MailSettings::class)->apply($manager);
        });

        // No reset-by-link pages: a forgotten password is replaced with a new default password
        // (by the Admin, or by the Super Admin from "Forgot your password?"). Fortify's own sign-up
        // is off too: "Register your home office" is RegisteredUserController (routes/auth.php).
        // Set here, before Fortify registers its routes.
        config(['fortify.features' => array_values(array_diff(config('fortify.features', []), [Features::resetPasswords(), Features::registration()]))]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // After signing in (password, passkey or email verification) every role goes through
        // /dashboard, which sends each user to their own home. Fortify's default is /home.
        config(['fortify.home' => '/dashboard', 'passkeys.redirect' => '/dashboard']);
    }
}
