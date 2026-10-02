<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra pay for a contractor who covered an absent contractor's work, entered by the
     * Super Admin during review, with a note saying whose work and when.
     */
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->decimal('coverage_pay', 12, 2)->default(0)->after('overtime_amount');
            $table->string('coverage_note')->nullable()->after('coverage_pay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->dropColumn(['coverage_pay', 'coverage_note']);
        });
    }
};
