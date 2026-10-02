<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;

/**
 * Attendance is locked and payroll is being calculated (Blueprint §14, step 12).
 */
class AttendanceLockedForPayroll extends ArkaNotification
{
    public function __construct(private readonly PayrollPeriod $period) {}

    protected function title(object $notifiable): string
    {
        return 'Attendance locked for payroll';
    }

    protected function message(object $notifiable): string
    {
        $range = $this->period->start_date->format('M j').' – '.$this->period->end_date->format('M j');

        return "Your attendance for {$range} is final and payroll is being prepared. Payslips are planned for {$this->period->release_date->format('M j')}.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.payslips.index', absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
