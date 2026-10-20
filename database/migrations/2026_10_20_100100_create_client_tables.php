<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clients and assignments (arka_system section 2). client_assignments is the junction
     * between contractors and clients; rates, schedules, time logs, attendance, overtime and
     * payroll point to it instead of repeating contractor + client.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('client_name');
            $table->unsignedInteger('break_allowance_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('client_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->string('employment_type', 20)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'client_id'], 'client_assignments_contractor_client_index');
        });

        // A rate is never edited: a change ends it and starts a new one.
        // UNIQUE (id, assignment_id) lets payroll check that its rate belongs to its assignment.
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->index()->constrained('client_assignments');
            $table->decimal('gross_pay', 12, 2);
            $table->enum('pay_frequency', ['weekly', 'semi_monthly', 'monthly']);
            $table->unsignedInteger('days_per_period')->default(11);
            $table->unsignedInteger('hours_per_day')->default(8);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['id', 'assignment_id'], 'rates_id_assignment_unique');
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('schedule_name');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['home_office_id', 'schedule_name'], 'work_schedules_home_office_name_unique');
        });

        // Either a work_schedules template or its own times, never both (CHECK added on MySQL).
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('client_assignments');
            $table->foreignId('work_schedule_id')->nullable()->constrained();
            $table->string('job_position');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // The days of the week a schedule covers. day_of_week: 1 = Monday … 7 = Sunday (ISO-8601).
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');

            $table->primary(['schedule_id', 'day_of_week']);
        });

        Schema::create('client_assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('client_id')->nullable()->constrained();
            $table->string('client_name')->nullable();
            $table->string('employment_type', 20)->nullable();
            $table->unsignedInteger('break_allowance_minutes')->nullable();
            $table->date('start_date');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users');
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
        Schema::dropIfExists('client_assignment_requests');
        Schema::dropIfExists('schedule_days');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('client_assignments');
        Schema::dropIfExists('clients');
    }
};
