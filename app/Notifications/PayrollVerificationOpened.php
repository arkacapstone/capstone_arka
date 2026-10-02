<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;

/**
 * Attendance verification is open (Blueprint §14): contractors review their attendance, may fix
 * days themselves on the day it opens, and submit it as verified. Also sent when the Super Admin unlocks attendance.
 */
class PayrollVerificationOpened extends ArkaNotification
{
    public function __construct(private readonly PayrollPeriod $period, private readonly bool $reopened = false) {}

    protected function title(object $notifiable): string
    {
        return $this->reopened ? 'Attendance reopened for review' : 'Review your attendance';
    }

    protected function message(object $notifiable): string
    {
        $range = $this->period->start_date->format('M j').' – '.$this->period->end_date->format('M j');
        $cutoff = $this->period->cutoff_date->format('M j');

        return $this->reopened
            ? "Attendance for {$range} is open again. Check your days and fix any time in/out today, until midnight."
            : "Payroll for {$range} is being prepared. Check your attendance, fix any time in/out today (until midnight), and submit it as verified before the {$cutoff} cutoff.";
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
