<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CHECK constraints and the views that read calculated values (arka_system section 11).
     * MySQL only: the tests run on SQLite, and the app computes the same values in PHP.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::CHECKS as $table => [$name, $rule]) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$rule})");
        }

        foreach (self::VIEWS as $name => $select) {
            DB::statement("CREATE OR REPLACE VIEW `{$name}` AS {$select}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::VIEWS) as $name) {
            DB::statement("DROP VIEW IF EXISTS `{$name}`");
        }

        foreach (self::CHECKS as $table => [$name]) {
            DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
        }
    }

    private const CHECKS = [
        'schedules' => ['schedules_template_or_custom_times', '(`work_schedule_id` IS NOT NULL AND `start_time` IS NULL AND `end_time` IS NULL) OR (`work_schedule_id` IS NULL AND `start_time` IS NOT NULL AND `end_time` IS NOT NULL)'],
        'schedule_days' => ['schedule_days_day_of_week_range', '`day_of_week` BETWEEN 1 AND 7'],
        'client_assignment_requests' => ['client_assignment_requests_one_client', '(`client_id` IS NOT NULL AND `client_name` IS NULL AND `break_allowance_minutes` IS NULL) OR (`client_id` IS NULL AND `client_name` IS NOT NULL)'],
        'cash_advances' => ['cash_advances_repayment_months_allowed', '`repayment_months` IN (2,3)'],
        'device_assignments' => ['device_assignments_end_together', '(`return_date` IS NULL AND `end_reason` IS NULL) OR (`return_date` IS NOT NULL AND `end_reason` IS NOT NULL)'],
        'rewards' => ['rewards_amount_matches_type', "(`reward_type` = 'cash' AND `amount` IS NOT NULL AND `amount` > 0) OR (`reward_type` = 'non_cash' AND `amount` IS NULL)"],
        'conversations' => ['conversations_name_only_for_group', "(`type` = 'group' AND `name` IS NOT NULL) OR (`type` <> 'group' AND `name` IS NULL)"],
    ];

    private const VIEWS = [
        'attendance_summary' => 'SELECT a.`id`, a.`assignment_id`, a.`date`,
              MIN(t.`time_in`) AS `time_in`, MAX(t.`time_out`) AS `time_out`,
              COALESCE(SUM(t.`break_minutes`), 0) AS `break_minutes`,
              ROUND(COALESCE(SUM(TIMESTAMPDIFF(MINUTE, t.`time_in`, t.`time_out`) - t.`break_minutes`), 0) / 60, 2) AS `actual_hours`,
              a.`late_minutes`, a.`undertime_minutes`, a.`status`, a.`leave_request_id`
            FROM `attendances` a
            LEFT JOIN `time_logs` t ON t.`assignment_id` = a.`assignment_id` AND t.`date` = a.`date` AND t.`time_out` IS NOT NULL
            GROUP BY a.`id`, a.`assignment_id`, a.`date`, a.`late_minutes`, a.`undertime_minutes`, a.`status`, a.`leave_request_id`',

        'payroll_summary' => 'SELECT p.*,
              COALESCE(ot.`total`, 0) AS `overtime_amount`, COALESCE(rw.`total`, 0) AS `reward_amount`,
              COALESCE(ca.`total`, 0) AS `cash_advance_deduction`, COALESCE(dd.`total`, 0) AS `device_deduction`,
              (p.`gross_pay` + p.`additional_pay` + COALESCE(ot.`total`, 0) + COALESCE(rw.`total`, 0)
                - p.`absence_deduction` - p.`late_deduction` - COALESCE(ca.`total`, 0) - COALESCE(dd.`total`, 0) - p.`other_deductions`) AS `net_pay`
            FROM `payroll` p
            LEFT JOIN (SELECT po.`payroll_id`, SUM(o.`amount`) AS `total` FROM `payroll_overtime` po JOIN `overtime_requests` o ON o.`id` = po.`overtime_request_id` GROUP BY po.`payroll_id`) ot ON ot.`payroll_id` = p.`id`
            LEFT JOIN (SELECT pr.`payroll_id`, SUM(r.`amount`) AS `total` FROM `payroll_rewards` pr JOIN `rewards` r ON r.`id` = pr.`reward_id` GROUP BY pr.`payroll_id`) rw ON rw.`payroll_id` = p.`id`
            LEFT JOIN (SELECT `payroll_id`, SUM(`amount`) AS `total` FROM `cash_advance_repayments` WHERE `payroll_id` IS NOT NULL GROUP BY `payroll_id`) ca ON ca.`payroll_id` = p.`id`
            LEFT JOIN (SELECT `payroll_id`, SUM(`amount`) AS `total` FROM `device_deductions` WHERE `payroll_id` IS NOT NULL GROUP BY `payroll_id`) dd ON dd.`payroll_id` = p.`id`',

        'cash_advance_balances' => "SELECT c.`id`, c.`contractor_id`, c.`amount`, c.`status`, c.`repayment_months`,
              ROUND(c.`amount` / c.`repayment_months`, 2) AS `monthly_installment`,
              COALESCE(SUM(r.`amount`), 0) AS `amount_repaid`,
              c.`amount` - COALESCE(SUM(r.`amount`), 0) AS `remaining_balance`,
              (c.`status` = 'approved' AND c.`amount` - COALESCE(SUM(r.`amount`), 0) <= 0) AS `is_repaid`
            FROM `cash_advances` c
            LEFT JOIN `cash_advance_repayments` r ON r.`advance_id` = c.`id`
            GROUP BY c.`id`, c.`contractor_id`, c.`amount`, c.`status`, c.`repayment_months`",

        'device_current_status' => "SELECT d.`id`, d.`device_name`, d.`serial_number`, d.`status` AS `condition_status`,
              da.`contractor_id` AS `current_contractor_id`,
              CASE WHEN d.`status` <> 'in_service' THEN d.`status` WHEN da.`id` IS NOT NULL THEN 'assigned' ELSE 'available' END AS `availability`
            FROM `devices` d
            LEFT JOIN `device_assignments` da ON da.`device_id` = d.`id` AND da.`return_date` IS NULL",

        'conversation_unread_counts' => 'SELECT p.`conversation_id`, p.`user_id`, COUNT(m.`id`) AS `unread_count`
            FROM `conversation_participants` p
            LEFT JOIN `messages` m ON m.`conversation_id` = p.`conversation_id` AND m.`user_id` <> p.`user_id`
              AND (p.`last_read_at` IS NULL OR m.`created_at` > p.`last_read_at`)
            GROUP BY p.`conversation_id`, p.`user_id`',
    ];
};
