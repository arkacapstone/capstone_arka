<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use App\Notifications\AdminDailyDigest;
use App\Services\Notifier;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Morning digest for Admins about the previous day: Incomplete attendance and missing
 * devotionals (Admin flow §IX). Nothing is sent when there is nothing to review.
 */
#[Signature('arka:admin-digest {--date= : The day to summarize (defaults to yesterday)}')]
#[Description('Send Admins a digest of incomplete attendance and missing devotionals')]
class SendAdminDigest extends Command
{
    public function handle(Notifier $notifier, SystemRules $rules): int
    {
        // A scheduled run follows System & Rules; an explicit --date always runs.
        if (! $this->option('date')) {
            if (! $rules->enabled('admin_digest_enabled')) {
                $this->info('The admin digest is turned off in System & Rules.');

                return self::SUCCESS;
            }

            if (now()->format('H:i') < $rules->get('admin_digest_time')) {
                $this->info('It is not time for the digest yet.');

                return self::SUCCESS;
            }
        }

        $date = $this->option('date') ? CarbonImmutable::parse($this->option('date'))->startOfDay() : CarbonImmutable::yesterday();

        $incomplete = Attendance::query()
            ->whereDate('date', $date)
            ->where('status', AttendanceStatus::Incomplete)
            ->count();

        $devotionalsMissing = User::query()
            ->workforce()
            ->active()
            ->where('created_at', '<', $date->addDay())
            ->whereDoesntHave('devotionals', fn ($query) => $query->whereDate('date', $date))
            ->count();

        if ($incomplete === 0 && $devotionalsMissing === 0) {
            $this->info('Nothing to report.');

            return self::SUCCESS;
        }

        if (! Cache::add("admin-digest:{$date->toDateString()}", true, now()->addDays(2))) {
            $this->info('The digest for this day was already sent.');

            return self::SUCCESS;
        }

        $notifier->admins(new AdminDailyDigest($date, $incomplete, $devotionalsMissing));

        $this->info("Digest sent: {$incomplete} incomplete, {$devotionalsMissing} devotionals missing.");

        return self::SUCCESS;
    }
}
