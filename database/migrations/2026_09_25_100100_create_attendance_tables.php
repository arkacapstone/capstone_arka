<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time tracking, attendance, corrections and leave requests (Blueprint §7, §8, §10).
     */
    public function up(): void
    {
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules');
            $table->date('date');
            $table->dateTime('time_in');
            $table->dateTime('time_out')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->dateTime('break_started_at')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->string('status', 20)->default('running');
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules');
            $table->date('date');
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('actual_hours', 5, 2)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->string('status', 20)->default('present');
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types');
            $table->timestamps();
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances');
            $table->foreignId('employee_id')->constrained('users');
            $table->date('date');
            $table->string('source', 20);
            $table->string('field_corrected', 20);
            $table->string('original_time_in')->nullable();
            $table->string('original_time_out')->nullable();
            $table->string('requested_time_in')->nullable();
            $table->string('requested_time_out')->nullable();
            $table->text('reason');
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->boolean('client_informed')->default(false);
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending_approval');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('time_logs');
    }
};
