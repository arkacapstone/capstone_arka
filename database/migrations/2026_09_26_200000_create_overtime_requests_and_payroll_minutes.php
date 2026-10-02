<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll Formula Reference:
     *  - Additional hours and lates are paid/deducted from exact minutes (18:20 = 18.333… h), not
     *    hours rounded to two decimals, so the sample's ₱7,869.70 comes out exactly.
     *  - Overtime is not a formula: the contractor files a ticket with the client handler who approved
     *    it, the Super Admin approves it with the amount, and it flows into the next payroll.
     */
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->unsignedInteger('additional_minutes')->default(0)->after('additional_hours');
            $table->unsignedInteger('late_minutes')->default(0)->after('late_hours');
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->date('date');
            $table->unsignedInteger('minutes');
            $table->string('client_handler', 120);
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('payroll_id')->nullable()->constrained('payroll')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');

        Schema::table('payroll', function (Blueprint $table) {
            $table->dropColumn(['additional_minutes', 'late_minutes']);
        });
    }
};
