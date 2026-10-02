<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll periods, payroll, cash advances and device deductions (Blueprint §11–§14).
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_name');
            $table->date('start_date')->index();
            $table->date('end_date');
            $table->date('cutoff_date');
            $table->date('release_date');
            $table->enum('pay_frequency', ['hourly', 'weekly', 'semi_monthly', 'monthly']);
            $table->enum('status', ['open', 'verification', 'locked', 'processed', 'released'])->default('open');
            $table->timestamps();
        });

        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('payroll_periods');
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('rate_id')->constrained('rates');
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('hourly_rate', 12, 4);
            $table->decimal('daily_rate', 12, 2);
            $table->decimal('additional_hours', 5, 2)->default(0);
            $table->decimal('additional_pay', 12, 2)->default(0);
            $table->decimal('days_absent', 4, 1)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->decimal('late_hours', 5, 2)->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('cash_advance_deduction', 12, 2)->default(0);
            $table->decimal('device_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->enum('status', ['draft', 'reviewed', 'approved', 'released'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->decimal('amount', 12, 2);
            $table->decimal('remaining_balance', 12, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'repaid', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->date('released_date')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('cash_advances');
            $table->foreignId('payroll_id')->nullable()->constrained('payroll');
            $table->decimal('amount', 12, 2);
            $table->date('repayment_date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('serial_number')->nullable()->unique();
            $table->string('device_type', 100)->nullable();
            $table->enum('status', ['available', 'assigned', 'lost', 'damaged', 'returned', 'retired'])->default('available');
            $table->timestamps();
        });

        Schema::create('device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->foreignId('employee_id')->constrained('users');
            $table->date('assigned_date');
            $table->date('return_date')->nullable();
            $table->boolean('acknowledgement_signed')->default(false);
            $table->enum('status', ['active', 'returned', 'lost', 'damaged'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('device_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_assignment_id')->constrained('device_assignments');
            $table->foreignId('payroll_id')->nullable()->constrained('payroll');
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_deductions');
        Schema::dropIfExists('device_assignments');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('cash_advance_repayments');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('payroll');
        Schema::dropIfExists('payroll_periods');
    }
};
