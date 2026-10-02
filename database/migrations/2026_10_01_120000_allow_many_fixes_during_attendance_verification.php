<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * During payroll verification a contractor may fix as many days as needed, but only on the day
     * the Super Admin opened verification (until midnight). Each fix is linked to the verification
     * so the Super Admin sees every change, with the time it replaced.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->timestamp('verification_opened_at')->nullable()->after('status');
        });

        // Periods already in verification: treat the last status change as the opening.
        DB::table('payroll_periods')->where('status', 'verification')->update(['verification_opened_at' => DB::raw('updated_at')]);

        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->foreignId('verification_id')->nullable()->after('employee_id')->constrained('attendance_verifications')->nullOnDelete();
        });

        foreach (DB::table('attendance_verifications')->whereNotNull('correction_id')->get(['id', 'correction_id']) as $verification) {
            DB::table('attendance_corrections')->where('id', $verification->correction_id)->update(['verification_id' => $verification->id]);
        }

        Schema::table('attendance_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correction_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_verifications', function (Blueprint $table) {
            $table->foreignId('correction_id')->nullable()->after('employee_id')->constrained('attendance_corrections');
        });

        foreach (DB::table('attendance_corrections')->whereNotNull('verification_id')->orderBy('id')->get(['id', 'verification_id']) as $correction) {
            DB::table('attendance_verifications')->where('id', $correction->verification_id)->update(['correction_id' => $correction->id]);
        }

        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verification_id');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('verification_opened_at');
        });
    }
};
