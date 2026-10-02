<?php

namespace App\Actions\Attendance;

/**
 * Describes which clock times a correction changes.
 */
final class FieldCorrected
{
    public static function from(?string $timeIn, ?string $timeOut): string
    {
        return match (true) {
            $timeIn !== null && $timeOut !== null => 'both',
            $timeIn !== null => 'time_in',
            default => 'time_out',
        };
    }

    public static function label(string $field): string
    {
        return match ($field) {
            'both' => 'Time in & out',
            'time_in' => 'Time in',
            'time_out' => 'Time out',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }
}
