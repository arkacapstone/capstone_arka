<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal details the account owner fills in on first login (Complete your profile).
     * Accounts that already exist are treated as complete so nobody is locked out.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone_number');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_number', 30)->nullable()->after('emergency_contact_name');
            $table->timestamp('profile_completed_at')->nullable()->after('must_change_password');
        });

        DB::table('users')->update(['profile_completed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address', 'emergency_contact_name', 'emergency_contact_number', 'profile_completed_at']);
        });
    }
};
