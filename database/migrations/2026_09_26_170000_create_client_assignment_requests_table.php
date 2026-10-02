<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An Admin gives a contractor a client together with its schedule; the Super Admin approves
     * it (setting the rate) or rejects it. Approval creates the rate and the schedule.
     */
    public function up(): void
    {
        Schema::create('client_assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('requested_by')->constrained('users');
            $table->longText('working_days');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('schedule_type', 20);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('rate_id')->nullable()->constrained('rates');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_assignment_requests');
    }
};
