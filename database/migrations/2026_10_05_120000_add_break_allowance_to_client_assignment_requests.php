<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Admin sets the client's break allowance when assigning it; it becomes the client's break once
 * the Super Admin approves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->unsignedInteger('break_allowance_minutes')->default(60)->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->dropColumn('break_allowance_minutes');
        });
    }
};
