<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bi-weekly is no longer offered; Monthly takes its place. Existing bi-weekly rows move to Semi-monthly.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['rates', 'payroll_periods'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY pay_frequency ENUM('hourly', 'weekly', 'bi_weekly', 'semi_monthly', 'monthly') NOT NULL");
            DB::table($table)->where('pay_frequency', 'bi_weekly')->update(['pay_frequency' => 'semi_monthly']);
            DB::statement("ALTER TABLE {$table} MODIFY pay_frequency ENUM('hourly', 'weekly', 'semi_monthly', 'monthly') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['rates', 'payroll_periods'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY pay_frequency ENUM('hourly', 'weekly', 'bi_weekly', 'semi_monthly', 'monthly') NOT NULL");
            DB::table($table)->where('pay_frequency', 'monthly')->update(['pay_frequency' => 'semi_monthly']);
            DB::statement("ALTER TABLE {$table} MODIFY pay_frequency ENUM('hourly', 'weekly', 'bi_weekly', 'semi_monthly') NOT NULL");
        }
    }
};
