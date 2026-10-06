<?php

namespace App\Http\Controllers\SuperAdmin\Workforce;

use App\Actions\Workforce\ManageDevices;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workforce Management → Devices. Company equipment lent to contractors, each with its own value:
 * a lost device is deducted at that value on the contractor's next payroll.
 */
class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([Device::STATUS_AVAILABLE, Device::STATUS_ASSIGNED, Device::STATUS_LOST])],
        ]);

        $devices = Device::query()
            ->with(['currentAssignment.employee:id,name,employee_code', 'assignments' => fn ($query) => $query->where('status', DeviceAssignment::STATUS_LOST)->with('employee:id,name', 'deduction.payroll:id,status')])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('device_name', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%")->orWhere('device_type', 'like', "%{$search}%")
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('device_name')
            ->orderBy('id')
            ->get()
            ->map(function (Device $device) {
                $lost = $device->assignments->first();

                return [
                    'id' => $device->id,
                    'name' => $device->device_name,
                    'type' => $device->device_type,
                    'serial' => $device->serial_number,
                    'value' => (float) $device->value,
                    'status' => $device->status,
                    'holder' => $device->currentAssignment ? [
                        'name' => $device->currentAssignment->employee->name,
                        'code' => $device->currentAssignment->employee->employee_code,
                        'since' => $device->currentAssignment->assigned_date->toDateString(),
                    ] : null,
                    'lostBy' => $lost?->employee->name,
                    'deducted' => $lost?->deduction ? (float) $lost->deduction->amount : null,
                ];
            });

        return Inertia::render('SuperAdmin/Workforce/Devices', [
            'devices' => $devices->all(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'counts' => [
                'total' => Device::query()->count(),
                'available' => Device::query()->where('status', Device::STATUS_AVAILABLE)->count(),
                'assigned' => Device::query()->where('status', Device::STATUS_ASSIGNED)->count(),
                'lost' => Device::query()->where('status', Device::STATUS_LOST)->count(),
            ],
            'contractors' => User::query()->workforce()->active()->orderBy('name')->get(['id', 'name', 'employee_code'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => "{$user->name} ({$user->employee_code})"])->all(),
            'types' => Device::TYPES,
        ]);
    }

    public function store(Request $request, ManageDevices $manage): RedirectResponse
    {
        $device = $this->validateDevice($request);
        // Optionally hand it to a contractor right away.
        $assignment = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues())->where('status', 'active')],
            'assigned_date' => ['nullable', 'required_with:employee_id', 'date'],
        ], attributes: ['employee_id' => 'contractor', 'assigned_date' => 'given on']);

        $created = DB::transaction(function () use ($manage, $device, $assignment) {
            $created = $manage->create($device);

            if (filled($assignment['employee_id'] ?? null)) {
                $manage->assign($created, User::findOrFail($assignment['employee_id']), CarbonImmutable::parse($assignment['assigned_date']));
            }

            return $created;
        });

        return back()->with('success', $created->status === Device::STATUS_ASSIGNED ? 'Device added and assigned.' : 'Device added.');
    }

    public function update(Request $request, Device $device, ManageDevices $manage): RedirectResponse
    {
        $manage->update($device, $this->validateDevice($request, $device));

        return back()->with('success', 'Device updated.');
    }

    public function assign(Request $request, Device $device, ManageDevices $manage): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues())->where('status', 'active')],
            'assigned_date' => ['required', 'date'],
        ], attributes: ['employee_id' => 'contractor', 'assigned_date' => 'assigned date']);

        $manage->assign($device, User::findOrFail($validated['employee_id']), CarbonImmutable::parse($validated['assigned_date']));

        return back()->with('success', 'Device assigned.');
    }

    public function returned(Device $device, ManageDevices $manage): RedirectResponse
    {
        $manage->markReturned($device);

        return back()->with('success', 'Device marked as returned.');
    }

    public function lost(Request $request, Device $device, ManageDevices $manage): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:255']]);

        $deduction = $manage->markLost($request->user(), $device, $validated['notes'] ?? null);

        return back()->with('success', 'Device marked as lost. ₱'.number_format((float) $deduction->amount, 2).' will be deducted from the contractor\'s next payroll.');
    }

    /**
     * @return array{device_name: string, device_type: ?string, serial_number: ?string, value: numeric}
     */
    private function validateDevice(Request $request, ?Device $device = null): array
    {
        return $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
            'device_type' => ['required', Rule::in(Device::TYPES)],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique(Device::class)->ignore($device)],
            'value' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
        ], attributes: ['device_name' => 'device name', 'device_type' => 'type', 'serial_number' => 'serial number']);
    }
}
