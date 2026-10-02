<?php

namespace App\Enums;

/**
 * Official attendance statuses (Blueprint §7), plus Incomplete for a day
 * with a time-in but no time-out (Admin flow §V).
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Undertime = 'undertime';
    case Absent = 'absent';
    case PaidLeave = 'paid_leave';
    case UnpaidLeave = 'unpaid_leave';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Undertime => 'Undertime',
            self::Absent => 'Absent',
            self::PaidLeave => 'Paid leave',
            self::UnpaidLeave => 'Unpaid leave',
            self::Incomplete => 'Incomplete',
        };
    }

    public function isLeave(): bool
    {
        return in_array($this, [self::PaidLeave, self::UnpaidLeave], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
