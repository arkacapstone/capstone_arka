<?php

namespace App\Enums;

/**
 * Cash advance lifecycle (Blueprint §13): requested → approved & released → repaid through
 * payroll or directly. Rejected and cancelled requests stay in history.
 */
enum CashAdvanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Repaid = 'repaid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::Approved => 'Released · repaying',
            self::Rejected => 'Rejected',
            self::Repaid => 'Fully repaid',
            self::Cancelled => 'Cancelled',
        };
    }
}
