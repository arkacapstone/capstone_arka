<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clients, schedules, rates and leave types (Blueprint §5, §6, §10).
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('client_code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_name');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('work_schedule_id')->nullable()->constrained('work_schedules');
            $table->string('job_position');
            $table->longText('working_days');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('schedule_type', 20)->default('flexible');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->decimal('gross_pay', 12, 2);
            $table->enum('pay_frequency', ['hourly', 'weekly', 'semi_monthly', 'monthly']);
            $table->integer('working_days')->default(11);
            $table->integer('hours_per_day')->default(8);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('leave_type_name');
            $table->boolean('is_paid')->default(false);
            $table->boolean('requires_proof')->default(false);
            $table->unsignedInteger('max_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('clients');
    }
};
