<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New accounts get an invite link instead of a temporary password. Only a hash of the
     * link's token is stored; it is cleared once the invite is accepted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_token', 64)->nullable()->unique()->after('profile_completed_at');
            $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['invitation_token']);
            $table->dropColumn(['invitation_token', 'invitation_sent_at']);
        });
    }
};
