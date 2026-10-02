<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client assignments and schedules are now separate steps: the Super Admin approves only the
     * client assignment (with its rate); the Admin schedules approved clients afterwards.
     */
    public function up(): void
    {
        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
        });

        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->dropColumn(['working_days', 'start_time', 'end_time', 'schedule_type', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->longText('working_days')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('schedule_type', 20)->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules');
        });
    }
};
