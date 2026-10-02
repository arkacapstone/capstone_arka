<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an Admin last reminded contractors to submit their attendance, and how many, so Period
     * Verification shows that the reminder went out.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('admin_submitted_by');
            $table->unsignedInteger('last_reminded_count')->nullable()->after('last_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['last_reminded_at', 'last_reminded_count']);
        });
    }
};
