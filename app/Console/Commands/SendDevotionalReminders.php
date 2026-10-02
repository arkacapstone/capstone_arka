<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DevotionalReminder;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Evening reminder for anyone who hasn't uploaded today's devotional (Contractor flow §X).
 * Sent at most once a day, and never as an alarm.
 */
#[Signature('arka:devotional-reminders')]
#[Description("Remind contractors who haven't submitted today's devotional")]
class SendDevotionalReminders extends Command
{
    public function handle(SystemRules $rules): int
    {
        $today = CarbonImmutable::today();

        if (! $rules->enabled('devotional_reminders_enabled')) {
            $this->info('Devotional reminders are turned off in System & Rules.');

            return self::SUCCESS;
        }

        if (now()->format('H:i') < $rules->get('devotional_reminder_time')) {
            $this->info('It is not time for reminders yet.');

            return self::SUCCESS;
        }

        if (! Cache::add("devotional-reminders:{$today->toDateString()}", true, $today->endOfDay())) {
            $this->info('Reminders were already sent today.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->workforce()
            ->active()
            ->whereDoesntHave('devotionals', fn ($query) => $query->whereDate('date', $today))
            ->get();

        Notification::send($recipients, new DevotionalReminder);

        $this->info("Reminded {$recipients->count()} ".str('person')->plural($recipients->count()).'.');

        return self::SUCCESS;
    }
}
