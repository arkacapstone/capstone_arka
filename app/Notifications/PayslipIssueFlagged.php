<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * A contractor flagged a question about a released payslip for the Super Admin (Contractor flow §VIII).
 */
class PayslipIssueFlagged extends ArkaNotification
{
    public function __construct(
        private readonly User $employee,
        private readonly PayrollPeriod $period,
        private readonly string $note,
    ) {}

    protected function title(object $notifiable): string
    {
        return 'Payslip question';
    }

    protected function message(object $notifiable): string
    {
        return "{$this->employee->name} flagged their {$this->period->period_name} payslip: \"{$this->note}\"";
    }

    protected function url(object $notifiable): ?string
    {
        return route('super-admin.payroll.show', $this->period, absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
