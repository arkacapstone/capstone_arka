<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notifications that come from time passing rather than a user action (Blueprint §16).
// Run `php artisan schedule:work` locally, or the `schedule:run` cron in production.
// Both check often and send once a day, at or after the time set in Super Admin → Notifications.
Schedule::command('arka:devotional-reminders')->everyFifteenMinutes();
Schedule::command('arka:admin-digest')->everyFifteenMinutes();
// Timers still running when their shift ends stop at the scheduled end time.
Schedule::command('arka:stop-finished-shifts')->everyMinute();
