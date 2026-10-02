<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;

/**
 * The Admin's morning digest for the previous day: attendance left Incomplete and
 * devotionals not submitted (Admin flow §IX).
 */
class AdminDailyDigest extends ArkaNotification
{
    public function __construct(
        private readonly CarbonImmutable $date,
        private readonly int $incomplete,
        private readonly int $devotionalsMissing,
    ) {}

    protected function title(object $notifiable): string
    {
        return 'Daily digest · '.$this->date->format('M j');
    }

    protected function message(object $notifiable): string
    {
        $parts = [];

        if ($this->incomplete > 0) {
            $parts[] = $this->incomplete === 1
                ? '1 attendance record is Incomplete (missing clock-out).'
                : "{$this->incomplete} attendance records are Incomplete (missing clock-out).";
        }

        if ($this->devotionalsMissing > 0) {
            $parts[] = $this->devotionalsMissing === 1
                ? "1 employee didn't submit a devotional."
                : "{$this->devotionalsMissing} employees didn't submit a devotional.";
        }

        return implode(' ', $parts);
    }

    protected function url(object $notifiable): ?string
    {
        $date = $this->date->toDateString();

        return $this->incomplete > 0
            ? route('admin.attendance.index', ['status' => 'incomplete', 'from' => $date, 'to' => $date], absolute: false)
            : route('admin.devotionals.index', ['date' => $date, 'status' => 'not_submitted'], absolute: false);
    }

    protected function category(): string
    {
        return 'attendance';
    }
}
