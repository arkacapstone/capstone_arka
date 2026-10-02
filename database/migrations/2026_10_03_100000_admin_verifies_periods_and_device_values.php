<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - The Admin reviews payroll verification and submits the verified period to the Super Admin,
     *   who can then process payroll.
     * - A lost device is deducted at that device's own value.
     * - The Super Admin may hold a contractor's payroll (e.g. lost company equipment); a held row
     *   is not released with the rest of the period.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->timestamp('admin_submitted_at')->nullable()->after('verification_opened_at');
            $table->foreignId('admin_submitted_by')->nullable()->after('admin_submitted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->decimal('value', 12, 2)->default(0)->after('device_type');
        });

        Schema::table('payroll', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('approved_at');
            $table->string('hold_reason')->nullable()->after('held_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->dropColumn(['held_at', 'hold_reason']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_submitted_by');
            $table->dropColumn('admin_submitted_at');
        });
    }
};
