<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Breaks differ per client, so the break allowance moves from the contractor to each client
     * assignment. The Admin sets it when adding the client. Existing assignments keep the
     * contractor's current allowance.
     */
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('employment_type');
        });

        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('employment_type');
        });

        foreach (DB::table('users')->whereNotNull('break_allowance_minutes')->pluck('break_allowance_minutes', 'id') as $id => $minutes) {
            DB::table('rates')->where('employee_id', $id)->update(['break_allowance_minutes' => $minutes]);
            DB::table('client_assignment_requests')->where('employee_id', $id)->update(['break_allowance_minutes' => $minutes]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('status');
        });

        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }
};
