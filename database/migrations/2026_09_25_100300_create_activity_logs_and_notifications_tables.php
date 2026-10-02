<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accountability and notification tables (Blueprint §16, Activity Logs).
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 100);
            $table->string('module', 50)->index();
            $table->string('reference_table', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
    }
};
