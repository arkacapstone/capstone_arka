<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Break allowance is set per account instead of one company-wide rule. Accounts without
     * one keep the old rule's value (60 minutes, unless System & Rules changed it).
     */
    public function up(): void
    {
        $current = (int) (DB::table('payroll_rules')->where('rule_key', 'default_break_allowance_minutes')->value('rule_value') ?? 60);

        DB::table('users')->whereNull('break_allowance_minutes')->update(['break_allowance_minutes' => $current]);
        DB::table('payroll_rules')->where('rule_key', 'default_break_allowance_minutes')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->nullable()->default(null)->change();
        });
    }
};
