<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rewards with an amount (e.g. a Bonus or Incentive from Performance & Rewards) are paid as
     * Additional Pay on the contractor's next payslip, once.
     */
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->decimal('reward_amount', 12, 2)->default(0)->after('overtime_amount');
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('payroll_id')->nullable()->after('awarded_at')->constrained('payroll')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_id');
        });

        Schema::table('payroll', function (Blueprint $table) {
            $table->dropColumn('reward_amount');
        });
    }
};
