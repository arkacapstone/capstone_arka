<?php

namespace App\Enums;

/**
 * Lifecycle of a payroll period (Blueprint §14):
 * Period open → Attendance verification (contractors fix once, the Admin reviews and submits the verified
 * period) → Payroll processed by the Super Admin (attendance locked) → Approval → Salary release.
 */
enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Verification = 'verification';
    case Locked = 'locked';
    case Processed = 'processed';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Period open',
            self::Verification => 'Verification',
            self::Locked => 'Attendance lock',
            self::Processed => 'Review & approve',
            self::Released => 'Released',
        };
    }

    /**
     * One-based position of this status in the payroll lifecycle.
     */
    public function step(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /**
     * The stage the primary action moves the period to, if any.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Open => self::Verification,
            self::Verification => self::Locked,
            self::Locked => self::Processed,
            self::Processed => self::Released,
            self::Released => null,
        };
    }

    /**
     * Locked can step back to Verification until payroll is approved. Opening verification
     * happens once, so Verification never goes back to Open.
     */
    public function previous(): ?self
    {
        return match ($this) {
            self::Locked => self::Verification,
            default => null,
        };
    }

    /**
     * Button label for moving from this stage to the next.
     */
    public function actionLabel(): ?string
    {
        return match ($this) {
            self::Open => 'Open attendance verification',
            self::Verification => 'Process payroll',
            self::Locked => 'Approve payroll',
            self::Processed => 'Release payslips',
            self::Released => null,
        };
    }

    /**
     * What happens when the primary action runs — shown as "Next step" and in the confirmation.
     */
    public function actionDescription(): ?string
    {
        return match ($this) {
            self::Open => 'Contractors will be notified to review and confirm their attendance. The Admin reviews their changes and submits the verified period to you.',
            self::Verification => 'The Admin has submitted the verified period. Attendance is locked and payroll is calculated for every active rate.',
            self::Locked => 'Every payroll row is approved. Adjustments are no longer possible after this.',
            self::Processed => 'Payslips become visible to contractors and each one is notified.',
            self::Released => null,
        };
    }

    /**
     * Button label for stepping back one stage.
     */
    public function revertLabel(): ?string
    {
        return match ($this) {
            self::Locked => 'Unlock attendance',
            default => null,
        };
    }

    public function revertDescription(): ?string
    {
        return match ($this) {
            self::Locked => 'Attendance reopens for corrections and the draft payroll is cleared. Contractors are notified, and the Admin submits the verified period again.',
            default => null,
        };
    }

    /**
     * Payroll rows can still be adjusted while attendance is locked and before approval.
     */
    public function allowsAdjustments(): bool
    {
        return $this === self::Locked;
    }
}
