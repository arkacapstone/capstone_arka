<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'primary_admin';
    case Admin = 'company_admin';
    case Employee = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Employee => 'Contractor',
        };
    }

    /**
     * Roles that work shifts and use the Contractor portal. An Admin is also a contractor
     * (Admin flow §X), so both track time, submit devotionals and receive payslips.
     *
     * @return list<self>
     */
    public static function workforce(): array
    {
        return [self::Employee, self::Admin];
    }

    /**
     * @return list<string>
     */
    public static function workforceValues(): array
    {
        return array_map(fn (self $role) => $role->value, self::workforce());
    }

    public function hasEmployeePortal(): bool
    {
        return in_array($this, self::workforce(), true);
    }
}
