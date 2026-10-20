<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KPI records, rewards and devotionals (arka_system section 8). Every reward comes from a
     * KPI record, so the contractor is read through kpi_records. A cash reward's payroll line
     * is in payroll_rewards.
     */
    public function up(): void
    {
        Schema::create('kpi_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->foreignId('period_id')->nullable()->constrained('payroll_periods');
            $table->decimal('kpi_score', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users');
            $table->dateTime('evaluated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->index()->constrained('kpi_records');
            $table->enum('reward_type', ['cash', 'non_cash']);
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('description');
            $table->foreignId('awarded_by')->nullable()->constrained('users');
            $table->dateTime('awarded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_rewards', function (Blueprint $table) {
            $table->foreignId('reward_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_id')->index()->constrained('payroll')->cascadeOnDelete();
        });

        Schema::create('devotionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained();
            $table->date('date');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('file_size');
            $table->dateTime('submitted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devotionals');
        Schema::dropIfExists('payroll_rewards');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('kpi_records');
    }
};
