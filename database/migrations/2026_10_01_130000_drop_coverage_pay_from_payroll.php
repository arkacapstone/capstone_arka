<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering someone's shift is paid as Additional Hours Pay (hours worked beyond the client's
     * expected hours, Payroll Formula & Scenario Reference, Person B), so the separate coverage
     * pay is removed. The full cash advance is now deducted at once, so the per-payroll installment
     * setting goes too.
     */
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->dropColumn(['coverage_pay', 'coverage_note']);
        });

        DB::table('payroll_rules')->where('rule_key', 'cash_advance_installment')->delete();
    }

    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->decimal('coverage_pay', 12, 2)->default(0)->after('overtime_amount');
            $table->string('coverage_note')->nullable()->after('coverage_pay');
        });
    }
};
