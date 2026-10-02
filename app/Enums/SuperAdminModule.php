<?php

namespace App\Enums;

/**
 * The Super Admin sidebar modules (Blueprint §3.1 and §21), in display order.
 */
enum SuperAdminModule: string
{
    case Dashboard = 'dashboard';
    case Workforce = 'workforce';
    case Payroll = 'payroll';
    case Payslips = 'payslips';
    case Requests = 'requests';
    case CashAdvances = 'cash-advances';
    case Performance = 'performance';
    case Rules = 'rules';
    case Reports = 'reports';
    case Notifications = 'notifications';
    case ActivityLogs = 'activity-logs';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Workforce => 'Workforce Management',
            self::Payroll => 'Payroll Management',
            self::Payslips => 'Payslips',
            self::Requests => 'Requests & Approvals',
            self::CashAdvances => 'Cash Advances',
            self::Performance => 'Performance & Rewards',
            self::Rules => 'System & Rules',
            self::Reports => 'Reports',
            self::Notifications => 'Notifications',
            self::ActivityLogs => 'Activity Logs',
        };
    }

    public function purpose(): string
    {
        return match ($this) {
            self::Dashboard => 'Provides an overall view of workforce status, payroll, pending actions, and important alerts.',
            self::Workforce => 'Manages users, employment classification, profiles, client assignments, and account status.',
            self::Payroll => 'Handles payroll using finalized attendance and approved payroll rules.',
            self::Payslips => 'Generates and stores payslips based on finalized payroll.',
            self::Requests => 'Handles higher-level requests that require Super Admin decisions, especially those that can affect money or contractor records.',
            self::CashAdvances => 'Tracks contractor cash advances and outstanding balances.',
            self::Performance => 'Records approved performance measures and associated rewards or recognition.',
            self::Rules => 'Controls the rules and settings used by ARKA. Tithes and devotional penalties are excluded from payroll.',
            self::Reports => 'Central reporting area that pulls information from applicable modules into PDF or CSV reports.',
            self::Notifications => 'Provides important system notifications to the Super Admin.',
            self::ActivityLogs => 'Provides accountability by recording important system actions.',
        };
    }

    /**
     * @return list<string>
     */
    public function includes(): array
    {
        return match ($this) {
            self::Dashboard => ['Workforce overview', 'Payroll overview', 'Pending approvals', 'Attendance summary', 'Alerts and notifications'],
            self::Workforce => ['Admin management', 'Contractor management', 'Full-Time / Part-Time classification', 'Contractor profiles', 'Client assignments', 'Account creation and management', 'Active / Inactive status'],
            self::Payroll => ['Payroll processing', 'Payroll calculation', 'Payroll review', 'Payroll records and history', 'Gross pay', 'Approved deductions', 'Net pay'],
            self::Payslips => ['Payslip generation', 'Current payslips', 'Previous payslips', 'Payslip records'],
            self::Requests => ['Leave requests', 'Financial/workforce requests', 'Approval / rejection', 'Request history'],
            self::CashAdvances => ['Cash advance requests', 'Approval', 'Released amount', 'Repayment tracking', 'Remaining balance', 'Cash advance history'],
            self::Performance => ['KPI records', 'KPI evaluation', 'Performance results', 'Rewards / incentives', 'Reward history'],
            self::Rules => ['Payroll rules', 'Administrative deduction rules', 'Device loss/damage rules', 'Cash advance deduction rules', 'Payroll cutoff settings', 'Payroll release settings', 'Basic system configuration'],
            self::Reports => ['Attendance', 'Devotional', 'Payroll', 'Payslip', 'Leave', 'Workforce', 'Schedule', 'Cash advance', 'Performance / KPI', 'Deduction', 'Device', 'PDF generation', 'CSV export'],
            self::Notifications => ['Approval notifications', 'Payroll notifications', 'Request notifications', 'System alerts', 'Announcements'],
            self::ActivityLogs => ['Login records', 'Account changes', 'Attendance changes', 'Payroll changes', 'Approval history', 'Important system actions'],
        };
    }

    public function routeName(): string
    {
        return 'super-admin.'.$this->value;
    }

    /**
     * @return list<array{key: string, label: string, href: string, routeName: string}>
     */
    public static function navigation(): array
    {
        return array_map(fn (self $module) => [
            'key' => $module->value,
            'label' => $module->label(),
            'href' => route($module->routeName()),
            'routeName' => $module->routeName(),
        ], self::cases());
    }
}
