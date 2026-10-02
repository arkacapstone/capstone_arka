<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each user's saved interface appearance: light, dark or follow the system.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('appearance', 10)->default('system')->after('break_allowance_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
