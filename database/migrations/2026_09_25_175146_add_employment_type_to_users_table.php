<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full-Time / Part-Time classification (Blueprint §1, §19).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employment_type', 20)->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
