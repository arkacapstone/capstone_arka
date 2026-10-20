<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll, payroll rules and cash advances (arka_system section 6). Overtime, rewards,
     * cash advance and device deductions, and net pay are not stored on payroll: they are
     * summed from their own tables (see the payroll_summary view).
     */
    public function up(): void
    {
        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('payroll_periods');
            $table->foreignId('assignment_id')->constrained('client_assignments');
            $table->unsignedBigInteger('rate_id');
            $table->decimal('gross_pay', 12, 2);
            $table->unsignedInteger('additional_minutes')->default(0);
            $table->decimal('additional_pay', 12, 2)->default(0);
            $table->decimal('days_absent', 4, 1)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->enum('status', ['draft', 'reviewed', 'approved', 'released'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->string('hold_reason')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'assignment_id'], 'payroll_period_assignment_unique');
            $table->index(['rate_id', 'assignment_id'], 'payroll_rate_assignment_index');
            // The rate used must belong to this same assignment.
            $table->foreign(['rate_id', 'assignment_id'], 'payroll_rate_assignment_foreign')
                ->references(['id', 'assignment_id'])->on('rates');
        });

        Schema::create('payroll_overtime', function (Blueprint $table) {
            $table->foreignId('overtime_request_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_id')->index()->constrained('payroll')->cascadeOnDelete();
        });

        Schema::create('payroll_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('rule_key', 100);
            $table->text('rule_value');
            $table->enum('data_type', ['string', 'integer', 'decimal', 'boolean', 'json']);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['home_office_id', 'rule_key'], 'payroll_rules_home_office_rule_key_unique');
        });

        // Remaining balance and "repaid" are not stored (see the cash_advance_balances view).
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->decimal('requested_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('repayment_months');
            $table->date('payday')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_advance_repayments');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('payroll_rules');
        Schema::dropIfExists('payroll_overtime');
        Schema::dropIfExists('payroll');
    }
};
