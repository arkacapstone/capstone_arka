<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contractor's attendance verification for one payroll period: the one day they fixed
     * themselves (if any) and when they submitted their attendance as verified.
     */
    public function up(): void
    {
        Schema::create('attendance_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('correction_id')->nullable()->constrained('attendance_corrections');
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_verifications');
    }
};
