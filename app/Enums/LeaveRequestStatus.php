<?php

namespace App\Enums;

/**
 * Leave request states (Contractor flow §VII). A request marked "client informed" without proof
 * is flagged Needs Verification for the Super Admin — nothing is rejected automatically.
 */
enum LeaveRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case NeedsVerification = 'needs_verification';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending approval',
            self::NeedsVerification => 'Needs verification',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Still waiting on the Super Admin, so the contractor can withdraw it.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::PendingApproval, self::NeedsVerification], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::PendingApproval->value, self::NeedsVerification->value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
