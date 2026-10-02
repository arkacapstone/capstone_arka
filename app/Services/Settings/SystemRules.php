<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System & Rules (Blueprint §3.1 module 8): the approved settings the rest of ARKA applies.
 * "System & Rules defines rules; Payroll applies them" (Blueprint §4).
 *
 * Payroll-related rules live in `payroll_rules`, everything else in `system_settings`.
 * Every rule has a default, so ARKA works before anything is saved. Tithes and devotional
 * penalties are deliberately not configurable: they are never payroll deductions.
 */
class SystemRules
{
    private const PAYROLL = 'payroll_rules';

    private const SYSTEM = 'system_settings';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * @return array<string, array{table: string, group: string, label: string, type: string, default: mixed, description: string, min?: int|float, max?: int|float}>
     */
    public static function definitions(): array
    {
        return [
            // Payroll rules
            'default_working_days' => ['table' => self::PAYROLL, 'group' => 'payroll', 'label' => 'Working days per pay period', 'type' => 'integer', 'default' => 11, 'min' => 1, 'max' => 31, 'description' => 'Pre-filled on new rates. Daily rate = gross ÷ working days.'],
            'default_hours_per_day' => ['table' => self::PAYROLL, 'group' => 'payroll', 'label' => 'Hours per day', 'type' => 'integer', 'default' => 8, 'min' => 1, 'max' => 24, 'description' => 'Pre-filled on new rates. Hourly rate = gross ÷ (working days × hours per day).'],
            'full_time_hours' => ['table' => self::PAYROLL, 'group' => 'payroll', 'label' => 'Full-Time hours per day', 'type' => 'integer', 'default' => 8, 'min' => 1, 'max' => 24, 'description' => 'Length of a Full-Time schedule. The end time is worked out from the start time.'],
            'part_time_hours' => ['table' => self::PAYROLL, 'group' => 'payroll', 'label' => 'Part-Time hours per day', 'type' => 'integer', 'default' => 4, 'min' => 1, 'max' => 24, 'description' => 'Length of a Part-Time schedule. The end time is worked out from the start time.'],

            // Administrative deduction rules
            'absence_deductions_enabled' => ['table' => self::PAYROLL, 'group' => 'deductions', 'label' => 'Deduct absences and unpaid leave', 'type' => 'boolean', 'default' => true, 'description' => 'Daily rate × days absent. Every scheduled working day not worked counts, including days with no attendance (e.g. after a contractor leaves mid-period). Paid leave is never deducted.'],
            'late_deductions_enabled' => ['table' => self::PAYROLL, 'group' => 'deductions', 'label' => 'Deduct late and undertime', 'type' => 'boolean', 'default' => true, 'description' => 'Hourly rate × late/undertime hours. There is no grace period.'],

            // Device loss: no fixed amount or cap. A lost device is deducted at its own value (Workforce → Devices).

            // Cash advance deduction rules
            'cash_advance_max_amount' => ['table' => self::PAYROLL, 'group' => 'cash_advances', 'label' => 'Maximum cash advance (₱)', 'type' => 'decimal', 'default' => 10000, 'min' => 0, 'max' => 10000000, 'description' => 'Largest single cash advance that can be requested.'],

            // Time tracking
            'timer_early_start_minutes' => ['table' => self::PAYROLL, 'group' => 'time_tracking', 'label' => 'Start timer early (minutes)', 'type' => 'integer', 'default' => 10, 'min' => 0, 'max' => 60, 'description' => 'How long before the shift starts a contractor may start the timer. No timer can be started after the shift ends.'],

            // Payroll cutoff and release (semi-monthly cycle, Blueprint §8)
            'first_cutoff_day' => ['table' => self::PAYROLL, 'group' => 'cutoff', 'label' => 'First cutoff day', 'type' => 'integer', 'default' => 10, 'min' => 1, 'max' => 28, 'description' => 'The first half of the month ends on this day.'],
            'first_release_day' => ['table' => self::PAYROLL, 'group' => 'cutoff', 'label' => 'First release day', 'type' => 'integer', 'default' => 15, 'min' => 1, 'max' => 31, 'description' => 'Salary for the first half is released on this day.'],
            'second_cutoff_day' => ['table' => self::PAYROLL, 'group' => 'cutoff', 'label' => 'Second cutoff day', 'type' => 'integer', 'default' => 25, 'min' => 2, 'max' => 31, 'description' => 'The second half of the month ends on this day.'],
            'second_release_day' => ['table' => self::PAYROLL, 'group' => 'cutoff', 'label' => 'Second release day', 'type' => 'integer', 'default' => 30, 'min' => 1, 'max' => 31, 'description' => 'Salary for the second half is released on this day (or the month\'s last day).'],

            // Basic system configuration
            'organization_name' => ['table' => self::SYSTEM, 'group' => 'system', 'label' => 'Organization name', 'type' => 'string', 'default' => 'Makarius Virtual Solutions', 'description' => 'Printed on payslips and reports.'],

            // Notification settings (Blueprint §16: the Super Admin manages system-wide notification settings)
            'devotional_reminders_enabled' => ['table' => self::SYSTEM, 'group' => 'notifications', 'label' => 'Evening devotional reminder', 'type' => 'boolean', 'default' => true, 'description' => 'A quiet reminder to anyone who has not uploaded today\'s devotional.'],
            'devotional_reminder_time' => ['table' => self::SYSTEM, 'group' => 'notifications', 'label' => 'Reminder time', 'type' => 'time', 'default' => '18:00', 'description' => 'Sent once a day at or after this time.'],
            'admin_digest_enabled' => ['table' => self::SYSTEM, 'group' => 'notifications', 'label' => 'Admin morning digest', 'type' => 'boolean', 'default' => true, 'description' => 'Yesterday\'s incomplete attendance and missing devotionals, sent to Admins.'],
            'admin_digest_time' => ['table' => self::SYSTEM, 'group' => 'notifications', 'label' => 'Digest time', 'type' => 'time', 'default' => '07:30', 'description' => 'Sent once a day at or after this time.'],
        ];
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::definitions()[$key]['default'] ?? null;
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    public function decimal(string $key): float
    {
        return (float) $this->get($key);
    }

    public function enabled(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * Every rule's current value (saved or default).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $saved = [];

        foreach ([self::PAYROLL => ['rule_key', 'rule_value'], self::SYSTEM => ['setting_key', 'setting_value']] as $table => [$key, $value]) {
            if (Schema::hasTable($table)) {
                $saved += DB::table($table)->pluck($value, $key)->all();
            }
        }

        $values = [];

        foreach (self::definitions() as $name => $definition) {
            $values[$name] = array_key_exists($name, $saved) ? $this->cast($saved[$name], $definition['type']) : $definition['default'];
        }

        return $this->values = $values;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<string> the labels of the rules that changed
     */
    public function update(array $changes, User $by): array
    {
        $current = $this->all();
        $changed = [];

        foreach (self::definitions() as $name => $definition) {
            if (! array_key_exists($name, $changes)) {
                continue;
            }

            $value = $this->cast($changes[$name], $definition['type']);

            if ($value === $current[$name]) {
                continue;
            }

            [$key, $column] = $definition['table'] === self::PAYROLL ? ['rule_key', 'rule_value'] : ['setting_key', 'setting_value'];

            DB::table($definition['table'])->updateOrInsert([$key => $name], [
                $column => $this->store($value, $definition['type']),
                'data_type' => $definition['type'] === 'time' ? 'string' : $definition['type'],
                'description' => $definition['label'],
                'updated_by' => $by->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]);

            $changed[] = $definition['label'];
        }

        $this->values = null;

        return $changed;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => round((float) $value, 2),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }

    private function store(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
