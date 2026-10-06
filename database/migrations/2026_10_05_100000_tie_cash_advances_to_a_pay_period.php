<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cash advance is requested during a pay period and repaid in full on that period's payday, up to
 * the contractor's gross pay for it. The Super Admin may approve less than was requested. The period
 * itself may not be created yet, so the payday and gross pay are kept on the advance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->decimal('requested_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('gross_pay', 12, 2)->nullable()->after('requested_amount');
            $table->date('payday')->nullable()->after('gross_pay');
        });

        // The gross pay is the only limit now, and requests are open the whole pay period.
        DB::table('payroll_rules')->whereIn('rule_key', ['cash_advance_max_amount', 'cash_advance_request_days'])->delete();
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn(['requested_amount', 'gross_pay', 'payday']);
        });
    }
};
