<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A period has one payroll row per contractor and client; a rate change during the period is
     * paid on that same row. Some databases already have this index, so it is only added if missing.
     */
    public function up(): void
    {
        if (Schema::hasIndex('payroll', 'payroll_period_emp_client_unique')) {
            return;
        }

        Schema::table('payroll', function (Blueprint $table) {
            $table->unique(['period_id', 'employee_id', 'client_id'], 'payroll_period_emp_client_unique');
        });
    }

    public function down(): void
    {
        // Left in place: it may have existed before this migration.
    }
};
