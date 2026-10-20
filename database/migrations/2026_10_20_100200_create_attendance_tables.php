<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll periods, leave, time logs, attendance and overtime (arka_system sections 3–5).
     * Attendance keeps no time in/out or hours: they come from that day's time_logs.
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('period_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cutoff_date');
            $table->date('release_date');
            $table->enum('pay_frequency', ['weekly', 'semi_monthly', 'monthly']);
            $table->enum('status', ['open', 'verification', 'locked', 'processed', 'released'])->default('open');
            $table->timestamp('verification_opened_at')->nullable();
            $table->timestamp('admin_submitted_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->unsignedInteger('last_reminded_count')->nullable();
            $table->timestamps();

            $table->index(['home_office_id', 'start_date'], 'payroll_periods_home_office_start_date_index');
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('leave_type_name');
            $table->boolean('is_paid')->default(false);
            $table->boolean('requires_proof')->default(false);
            $table->unsignedInteger('max_days')->nullable();
            $table->timestamps();

            $table->unique(['home_office_id', 'leave_type_name'], 'leave_types_home_office_name_unique');
        });

        // A timer's state is not stored: completed when time_out is set, on break when
        // break_started_at is set, otherwise running.
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('client_assignments');
            $table->date('date');
            $table->dateTime('time_in');
            $table->dateTime('time_out')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->dateTime('break_started_at')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'date'], 'time_logs_assignment_date_index');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->boolean('client_informed')->default(false);
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending_approval');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('client_assignments');
            $table->date('date');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->string('status', 20)->default('present');
            $table->foreignId('leave_request_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'date'], 'attendances_assignment_date_unique');
        });

        Schema::create('attendance_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained();
            $table->dateTime('verified_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->timestamps();

            $table->unique(['period_id', 'contractor_id'], 'attendance_verifications_period_contractor_unique');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('client_assignments');
            $table->date('date');
            $table->foreignId('verification_id')->nullable()->constrained('attendance_verifications')->nullOnDelete();
            $table->string('source', 20);
            $table->string('field_corrected', 20);
            $table->dateTime('original_time_in')->nullable();
            $table->dateTime('original_time_out')->nullable();
            $table->dateTime('requested_time_in')->nullable();
            $table->dateTime('requested_time_out')->nullable();
            $table->text('reason');
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'date'], 'attendance_corrections_assignment_date_index');
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('client_assignments');
            $table->date('date');
            $table->unsignedInteger('minutes');
            $table->string('client_handler', 120);
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_verifications');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('time_logs');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('payroll_periods');
    }
};
