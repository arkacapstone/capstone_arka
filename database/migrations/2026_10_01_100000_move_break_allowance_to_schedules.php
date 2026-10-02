<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The break allowance is set on each schedule (Admin → Scheduling → Add schedule) instead of on
     * the client assignment. Existing schedules take the break of their contractor's assignment
     * with that client.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('schedule_type');
        });

        foreach (DB::table('rates')->orderBy('effective_date')->get(['employee_id', 'client_id', 'break_allowance_minutes']) as $rate) {
            DB::table('schedules')
                ->where('employee_id', $rate->employee_id)
                ->where('client_id', $rate->client_id)
                ->update(['break_allowance_minutes' => $rate->break_allowance_minutes]);
        }

        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });

        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('employment_type');
        });

        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('employment_type');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }
};
