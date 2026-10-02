<?php

namespace App\Notifications;

use App\Enums\CashAdvanceStatus;
use App\Models\CashAdvance;

/**
 * Tells the contractor what was decided on their cash advance and how it is repaid.
 */
class CashAdvanceDecided extends ArkaNotification
{
    public function __construct(private readonly CashAdvance $advance) {}

    protected function title(object $notifiable): string
    {
        return $this->advance->status === CashAdvanceStatus::Approved ? 'Cash advance released' : 'Cash advance request closed';
    }

    protected function message(object $notifiable): string
    {
        $amount = '₱'.number_format((float) $this->advance->amount, 2);

        return $this->advance->status === CashAdvanceStatus::Approved
            ? "Your {$amount} cash advance was released. Repayments show as a separate line on your payslips until it is paid."
            : "Your {$amount} cash advance request was closed without release. You can talk to the Super Admin about it.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.cash-advances.index', absolute: false);
    }

    protected function category(): string
    {
        return 'payslip';
    }
}
