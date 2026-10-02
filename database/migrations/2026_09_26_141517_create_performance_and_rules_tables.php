<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance & Rewards and System & Rules (Blueprint §3.1 modules 7 and 8).
     * Mirrors the tables that already exist in the ARKA database.
     */
    public function up(): void
    {
        Schema::create('kpi_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('period_id')->nullable()->constrained('payroll_periods');
            $table->decimal('kpi_score', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users');
            $table->dateTime('evaluated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('kpi_id')->nullable()->constrained('kpi_records');
            $table->string('reward_type', 50)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users');
            $table->dateTime('awarded_at')->nullable();
            $table->timestamps();
        });

        foreach (['system_settings' => 'setting_key', 'payroll_rules' => 'rule_key'] as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($name, $key) {
                $table->id();
                $table->string($key, 100)->unique();
                $table->text($name === 'system_settings' ? 'setting_value' : 'rule_value');
                $table->enum('data_type', ['string', 'integer', 'decimal', 'boolean', 'json']);
                $table->string('description')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_rules');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('kpi_records');
    }
};
