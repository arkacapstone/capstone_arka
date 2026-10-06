<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the Admin submits the verified period, contractors who never submitted are submitted
     * for them with their attendance as recorded; they are accountable for not fixing it.
     * This marks those submissions so they are not mistaken for ones the contractor made.
     */
    public function up(): void
    {
        Schema::table('attendance_verifications', function (Blueprint $table) {
            $table->boolean('auto_submitted')->default(false)->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_verifications', function (Blueprint $table) {
            $table->dropColumn('auto_submitted');
        });
    }
};
