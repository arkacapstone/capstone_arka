<?php

namespace App\Enums;

/**
 * The interface theme a user picks in Profile. System follows the device setting.
 */
enum Appearance: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
