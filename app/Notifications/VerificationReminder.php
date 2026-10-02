<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Sent by an Admin to contractors who have not submitted their attendance as verified: it is
 * already the cut-off, so submit now, with how much time is left to make changes.
 */
class VerificationReminder extends ArkaNotification
{
    private readonly ?CarbonImmutable $deadline;

    private readonly bool $canStillFix;

    private readonly CarbonImmutable $sentAt;

    public function __construct(private readonly PayrollPeriod $period)
    {
        // Worked out when the Admin sends it, so the time left reads the same later on.
        $this->sentAt = CarbonImmutable::now();
        $this->deadline = $period->fixDeadline();
        $this->canStillFix = $period->fixWindowOpen($this->sentAt);
    }

    protected function title(object $notifiable): string
    {
        return 'Submit your attendance now';
    }

    protected function message(object $notifiable): string
    {
        $intro = "It's already the cut-off for {$this->period->period_name}. Please submit your attendance as verified now.";

        if (! $this->canStillFix || $this->deadline === null) {
            return "{$intro} Fixing has closed, so submit your attendance as it is.";
        }

        $left = $this->sentAt->diffForHumans($this->deadline, CarbonInterface::DIFF_ABSOLUTE, parts: 2);

        return "{$intro} You have {$left} left to make changes (until {$this->deadline->format('g:i A, M j')}).";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.attendance.index', ['tab' => 'verification'], absolute: false);
    }

    protected function category(): string
    {
        return 'attendance';
    }
}
