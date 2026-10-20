<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System settings and chat (arka_system sections 9–10). A message's sender must be a
     * participant of its conversation (composite foreign key).
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('setting_key', 100);
            $table->text('setting_value');
            $table->enum('data_type', ['string', 'integer', 'decimal', 'boolean', 'json']);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['home_office_id', 'setting_key'], 'system_settings_home_office_setting_key_unique');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'group', 'community'])->default('direct');
            $table->string('name')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_read_at')->nullable();

            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_index');
            $table->index(['conversation_id', 'user_id'], 'messages_participant_index');
            $table->foreign(['conversation_id', 'user_id'], 'messages_participant_foreign')
                ->references(['conversation_id', 'user_id'])->on('conversation_participants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('system_settings');
    }
};
