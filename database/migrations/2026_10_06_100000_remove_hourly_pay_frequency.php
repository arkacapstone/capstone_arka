<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pay is per period only: Weekly, Semi-monthly or Monthly. Hourly is no longer offered.
     * Any hourly row left over moves to Semi-monthly, the default cycle.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['rates', 'payroll_periods'] as $table) {
            DB::table($table)->where('pay_frequency', 'hourly')->update(['pay_frequency' => 'semi_monthly']);
            DB::statement("ALTER TABLE {$table} MODIFY pay_frequency ENUM('weekly', 'semi_monthly', 'monthly') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['rates', 'payroll_periods'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY pay_frequency ENUM('hourly', 'weekly', 'semi_monthly', 'monthly') NOT NULL");
        }
    }
};
