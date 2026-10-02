<?php

namespace App\Actions\Workforce;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceDeduction;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Company equipment lent to contractors (Blueprint §11, device loss/damage). A lost device is
 * deducted at that device's own value, never a fixed amount, on the contractor's next payroll.
 */
class ManageDevices
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array{device_name: string, device_type?: ?string, serial_number?: ?string, value: numeric}  $attributes
     */
    public function create(array $attributes): Device
    {
        $device = Device::create([...$attributes, 'status' => Device::STATUS_AVAILABLE]);
        $this->activity->log('workforce', 'Added device', $device, $this->describe($device));

        return $device;
    }

    /**
     * @param  array{device_name: string, device_type?: ?string, serial_number?: ?string, value: numeric}  $attributes
     */
    public function update(Device $device, array $attributes): Device
    {
        $device->update($attributes);
        $this->activity->log('workforce', 'Updated device', $device, $this->describe($device));

        return $device;
    }

    public function assign(Device $device, User $contractor, CarbonImmutable $date): DeviceAssignment
    {
        if ($device->status !== Device::STATUS_AVAILABLE) {
            throw ValidationException::withMessages(['employee_id' => 'Only an available device can be assigned.']);
        }

        $assignment = DB::transaction(function () use ($device, $contractor, $date) {
            $device->update(['status' => Device::STATUS_ASSIGNED]);

            return $device->assignments()->create([
                'employee_id' => $contractor->id,
                'assigned_date' => $date,
                'status' => DeviceAssignment::STATUS_ACTIVE,
            ]);
        });

        $this->activity->log('workforce', 'Assigned device', $device, "{$this->describe($device)} → {$contractor->name}");

        return $assignment;
    }

    public function markReturned(Device $device): Device
    {
        $assignment = $this->currentAssignment($device);

        DB::transaction(function () use ($device, $assignment) {
            $assignment->update(['status' => DeviceAssignment::STATUS_RETURNED, 'return_date' => CarbonImmutable::today()]);
            $device->update(['status' => Device::STATUS_AVAILABLE]);
        });

        $this->activity->log('workforce', 'Device returned', $device, "{$this->describe($device)} ← {$assignment->employee->name}");

        return $device;
    }

    /**
     * The contractor who had the device is charged its value; payroll deducts it on their next payslip.
     */
    public function markLost(User $superAdmin, Device $device, ?string $notes = null): DeviceDeduction
    {
        $assignment = $this->currentAssignment($device);

        if ((float) $device->value <= 0) {
            throw ValidationException::withMessages(['value' => 'Set this device\'s value first. A lost device is deducted at its own value.']);
        }

        $deduction = DB::transaction(function () use ($superAdmin, $device, $assignment, $notes) {
            $assignment->update(['status' => DeviceAssignment::STATUS_LOST, 'notes' => $notes]);
            $device->update(['status' => Device::STATUS_LOST]);

            return $assignment->deduction()->create([
                'amount' => $device->value,
                'reason' => "Lost device: {$this->describe($device)}".($notes ? " · {$notes}" : ''),
                'approved_by' => $superAdmin->id,
                'approved_at' => now(),
            ]);
        });

        $this->activity->log('workforce', 'Device lost', $device, "{$this->describe($device)} · {$assignment->employee->name} · ₱".number_format((float) $device->value, 2));

        return $deduction;
    }

    private function currentAssignment(Device $device): DeviceAssignment
    {
        return $device->currentAssignment()->with('employee:id,name')->first()
            ?? throw ValidationException::withMessages(['device' => 'This device is not assigned to anyone.']);
    }

    private function describe(Device $device): string
    {
        return $device->device_name.($device->serial_number ? " ({$device->serial_number})" : '');
    }
}
