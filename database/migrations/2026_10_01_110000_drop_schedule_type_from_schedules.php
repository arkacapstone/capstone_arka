<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every schedule is strict: one minute past the start is Late, with no grace period,
     * so there is no schedule type to choose.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('schedule_type', 20)->default('strict')->after('end_time');
        });
    }
};
