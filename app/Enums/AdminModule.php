<?php

namespace App\Enums;

/**
 * The Admin sidebar (Admin flow §II, Blueprint §21). No money-related modules.
 */
enum AdminModule: string
{
    case Dashboard = 'dashboard';
    case Employees = 'employees';
    case Scheduling = 'scheduling';
    case Attendance = 'attendance';
    case Devotionals = 'devotionals';
    case Reports = 'reports';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Employees => 'Contractor Management',
            self::Scheduling => 'Scheduling',
            self::Attendance => 'Attendance Management',
            self::Devotionals => 'Devotional Management',
            self::Reports => 'Reports',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Dashboard => 'admin.dashboard',
            // Scheduling opens on its Clients tab: a contractor needs an approved client before a schedule.
            self::Scheduling => 'admin.scheduling.clients.index',
            default => "admin.{$this->value}.index",
        };
    }

    /**
     * @return list<array{key: string, label: string, href: string, routeName: string, match: string}>
     */
    public static function navigation(): array
    {
        return array_map(fn (self $module) => [
            'key' => $module->value,
            'label' => $module->label(),
            'href' => route($module->routeName()),
            'routeName' => $module->routeName(),
            'match' => "admin.{$module->value}.*",
        ], self::cases());
    }
}
