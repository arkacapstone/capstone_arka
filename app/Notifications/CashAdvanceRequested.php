<?php

namespace App\Notifications;

use App\Models\CashAdvance;

/**
 * A cash advance request is waiting for the Super Admin (Blueprint §15).
 */
class CashAdvanceRequested extends ArkaNotification
{
    public function __construct(private readonly CashAdvance $advance) {}

    protected function title(object $notifiable): string
    {
        return 'Cash advance request';
    }

    protected function message(object $notifiable): string
    {
        $this->advance->loadMissing('employee:id,name');

        return "{$this->advance->employee->name} requested ₱".number_format((float) $this->advance->amount, 2).": {$this->advance->reason}";
    }

    protected function url(object $notifiable): ?string
    {
        return route('super-admin.cash-advances', absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
