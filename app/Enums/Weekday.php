<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum Weekday: string
{
    case Monday = 'mon';
    case Tuesday = 'tue';
    case Wednesday = 'wed';
    case Thursday = 'thu';
    case Friday = 'fri';
    case Saturday = 'sat';
    case Sunday = 'sun';

    public static function of(CarbonInterface $date): self
    {
        return self::from(strtolower($date->format('D')));
    }

    public function short(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $day) => ['value' => $day->value, 'label' => $day->short()], self::cases());
    }
}
