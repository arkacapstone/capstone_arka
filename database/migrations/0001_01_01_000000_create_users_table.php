<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Home offices and accounts (arka_system sections 0 and 1).
     *
     * users is split: users (login/account), user_profiles (personal info) and contractors
     * (anchor for every contractor-owned record). contractors.id is set to the user's id
     * (a shared primary key), so a contractor_id and the user's id are the same number.
     */
    public function up(): void
    {
        Schema::create('home_offices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Optional; printed on payslips under the name.
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('employee_code');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->enum('role', ['primary_admin', 'company_admin', 'contractor'])->index();
            $table->string('status', 20)->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->string('appearance', 10)->default('system');
            $table->rememberToken();
            // Equals home_office_id only for the Super Admin, so the unique index below allows
            // one Super Admin per home office. Computed by the database, never written by the app.
            $table->unsignedBigInteger('super_admin_home_office_id')
                ->storedAs("CASE WHEN role = 'primary_admin' THEN home_office_id END")
                ->nullable();
            $table->timestamps();

            $table->unique(['home_office_id', 'employee_code'], 'users_home_office_employee_code_unique');
            $table->unique('super_admin_home_office_id', 'users_one_super_admin_per_home_office');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->date('birthday')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number', 30)->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('home_offices');
    }
};
