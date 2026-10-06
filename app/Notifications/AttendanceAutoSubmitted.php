<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;

/**
 * The contractor never submitted their attendance, so it was submitted for them, as recorded, when
 * the Admin submitted the verified period. Not fixing it was their responsibility.
 */
class AttendanceAutoSubmitted extends ArkaNotification
{
    public function __construct(private readonly PayrollPeriod $period) {}

    protected function title(object $notifiable): string
    {
        return 'Attendance submitted for you';
    }

    protected function message(object $notifiable): string
    {
        return "You did not submit your attendance for {$this->period->period_name}, so it was submitted as recorded when the Admin submitted the period. Payroll uses it as is.";
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
