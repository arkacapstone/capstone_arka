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

    /**
     * ISO-8601 day number as stored in schedule_days.day_of_week: 1 = Monday … 7 = Sunday.
     */
    public function isoNumber(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public static function fromIsoNumber(int $number): self
    {
        return self::cases()[$number - 1];
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
