<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The break allowance follows the client's own schedule, the same for Full-Time and Part-Time
 * contractors, so it is set per client instead of on each contractor's schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('client_code');
        });

        // Each client keeps the allowance of its most recent schedule.
        foreach (DB::table('schedules')->orderBy('start_date')->orderBy('id')->get(['client_id', 'break_allowance_minutes']) as $schedule) {
            DB::table('clients')->where('id', $schedule->client_id)->update(['break_allowance_minutes' => $schedule->break_allowance_minutes]);
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('end_time');
        });

        foreach (DB::table('clients')->get(['id', 'break_allowance_minutes']) as $client) {
            DB::table('schedules')->where('client_id', $client->id)->update(['break_allowance_minutes' => $client->break_allowance_minutes]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }
};
