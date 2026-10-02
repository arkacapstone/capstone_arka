<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;

/**
 * The contractor's payslip is available (Contractor flow §X, "payslip released").
 */
class PayslipReleased extends ArkaNotification
{
    public function __construct(private readonly PayrollPeriod $period) {}

    protected function title(object $notifiable): string
    {
        return 'Your payslip is ready';
    }

    protected function message(object $notifiable): string
    {
        return "Your payslip for {$this->period->period_name} is available. Open it to see every line of your pay.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.payslips.index', ['open' => $this->period->id], absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
