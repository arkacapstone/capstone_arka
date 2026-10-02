<?php

namespace App\Enums;

enum PayFrequency: string
{
    case Hourly = 'hourly';
    case Weekly = 'weekly';
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Weekly => 'Weekly',
            self::SemiMonthly => 'Semi-monthly',
            self::Monthly => 'Monthly',
        };
    }
}
