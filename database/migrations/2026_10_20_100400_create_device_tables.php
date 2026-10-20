<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devices (arka_system section 7). devices.status is the device's own condition only;
     * whether it is assigned or available comes from device_assignments.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_office_id')->constrained();
            $table->string('device_name');
            $table->string('serial_number')->nullable();
            $table->string('device_type', 100)->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->enum('status', ['in_service', 'lost', 'damaged', 'retired'])->default('in_service');
            $table->timestamps();

            $table->unique(['home_office_id', 'serial_number'], 'devices_home_office_serial_number_unique');
        });

        // Open while return_date is empty; end_reason says how it ended.
        Schema::create('device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained();
            $table->foreignId('contractor_id')->constrained();
            $table->date('assigned_date');
            $table->date('return_date')->nullable();
            $table->boolean('acknowledgement_signed')->default(false);
            $table->enum('end_reason', ['returned', 'lost', 'damaged'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'return_date'], 'device_assignments_device_open_index');
        });

        Schema::create('device_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_assignment_id')->constrained();
            $table->foreignId('payroll_id')->nullable()->constrained('payroll');
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_deductions');
        Schema::dropIfExists('device_assignments');
        Schema::dropIfExists('devices');
    }
};
