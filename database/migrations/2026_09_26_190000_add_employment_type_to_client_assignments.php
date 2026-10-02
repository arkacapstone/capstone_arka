<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each client assignment is Full-Time or Part-Time (chosen by the Admin). A contractor with any
     * Full-Time client is a Full-Time contractor. Admins type the client's name: an existing client
     * is reused, a new one is created when the Super Admin approves.
     */
    public function up(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->string('employment_type', 20)->nullable()->after('client_id');
        });

        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
            $table->string('client_name')->nullable()->after('client_id');
            $table->string('employment_type', 20)->nullable()->after('client_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_assignment_requests', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'employment_type']);
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
