<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Anything else we should know?" — the optional note on a leave request (Employee flow §VII).
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('proof_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
