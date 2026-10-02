<?php

namespace App\Enums;

enum TimeLogStatus: string
{
    case Running = 'running';
    case OnBreak = 'on_break';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::OnBreak => 'On break',
            self::Completed => 'Completed',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
