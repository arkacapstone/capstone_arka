<?php

namespace App\Notifications;

use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * The Admin reviewed the contractors' changes and submitted the verified period: the Super Admin
 * can now process payroll.
 */
class VerifiedPeriodSubmitted extends ArkaNotification
{
    public function __construct(private readonly PayrollPeriod $period, private readonly User $admin) {}

    protected function title(object $notifiable): string
    {
        return 'Verified period ready for payroll';
    }

    protected function message(object $notifiable): string
    {
        return "{$this->admin->name} submitted the verified attendance for {$this->period->period_name}. You can now process payroll.";
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
