<?php

namespace App\Enums;

/**
 * How often a rate is paid and a payroll period runs. Pay is always per period, never hourly.
 */
enum PayFrequency: string
{
    case Weekly = 'weekly';
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::SemiMonthly => 'Semi-monthly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()], self::cases());
    }
}
