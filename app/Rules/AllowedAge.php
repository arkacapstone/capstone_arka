<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A birthday is accepted only when the person is at least MIN years old and younger than MAX
 * (10 to 64 today). Pair it with the `date` rule so non-dates get the usual message first.
 */
class AllowedAge implements ValidationRule
{
    public const MIN = 10;

    public const MAX = 65;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $age = Carbon::parse($value)->age;
        } catch (Throwable) {
            return;
        }

        if ($age < self::MIN) {
            $fail('The :attribute must be at least '.self::MIN.' years ago.');
        } elseif ($age >= self::MAX) {
            $fail('Only people younger than '.self::MAX.' can be added. Check the :attribute.');
        }
    }
}
