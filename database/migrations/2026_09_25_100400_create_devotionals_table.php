<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily devotional proof uploads (Blueprint §9).
     */
    public function up(): void
    {
        Schema::create('devotionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
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
    }
};
