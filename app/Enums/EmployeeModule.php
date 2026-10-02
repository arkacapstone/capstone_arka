<?php

namespace App\Enums;

/**
 * The Contractor portal sidebar (Contractor flow, "Sidebar Navigation"). Time History is
 * nested under Time Tracker rather than being a top-level item.
 */
enum EmployeeModule: string
{
    case Dashboard = 'dashboard';
    case TimeTracker = 'time-tracker';
    case TimeHistory = 'time-history';
    case Devotional = 'devotional';
    case Attendance = 'my-attendance';
    case Leave = 'leave';
    case Overtime = 'overtime';
    case Payslip = 'payslip';
    case CashAdvance = 'cash-advance';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::TimeTracker => 'Time Tracker',
            self::TimeHistory => 'Time History',
            self::Devotional => 'Devotional',
            self::Attendance => 'Attendance',
            self::Leave => 'Leave Request',
            self::Overtime => 'Overtime',
            self::Payslip => 'Payslip',
            self::CashAdvance => 'Cash Advance',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Dashboard => 'employee.dashboard',
            self::TimeTracker => 'employee.time-tracker.index',
            self::TimeHistory => 'employee.time-history.index',
            self::Devotional => 'employee.devotionals.index',
            self::Attendance => 'employee.attendance.index',
            self::Leave => 'employee.leave.index',
            self::Overtime => 'employee.overtime.index',
            self::Payslip => 'employee.payslips.index',
            self::CashAdvance => 'employee.cash-advances.index',
        };
    }

    /**
     * @return array{key: string, label: string, href: string, routeName: string, match: string}
     */
    public function item(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'href' => route($this->routeName()),
            'routeName' => $this->routeName(),
            'match' => preg_replace('/\.index$/', '.*', $this->routeName()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function navigation(): array
    {
        $items = [];

        foreach (self::cases() as $module) {
            if ($module === self::TimeHistory) {
                continue;
            }

            $item = $module->item();

            if ($module === self::TimeTracker) {
                $item['children'] = [self::TimeHistory->item()];
            }

            $items[] = $item;
        }

        return $items;
    }
}
