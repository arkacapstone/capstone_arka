<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceDeduction;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\User;
use App\Notifications\PayslipReleased;
use App\Services\Payslips\EmployeePayslips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeviceAndPayrollHoldTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $contractor;

    private User $other;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 10:00:00');
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->contractor = User::factory()->create(['name' => 'Christian Mae']);
        $this->other = User::factory()->create(['name' => 'Zed Other']);
        $client = Client::factory()->create();

        foreach ([$this->contractor, $this->other] as $contractor) {
            Rate::factory()->for($contractor, 'employee')->for($client)->create([
                'gross_pay' => 30000,
                'pay_frequency' => PayFrequency::SemiMonthly,
                'working_days' => 11,
                'hours_per_day' => 8,
                'effective_date' => '2026-01-01',
            ]);
        }

        $this->period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Verification, 'verification_opened_at' => now()->subDay(), 'admin_submitted_at' => now()]);
    }

    private function addDevice(string $name, float $value): Device
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.workforce.devices.store'), ['device_name' => $name, 'device_type' => 'Laptop', 'serial_number' => "SN-{$name}", 'value' => $value])
            ->assertSessionHasNoErrors();

        return Device::query()->where('device_name', $name)->sole();
    }

    private function loseDevice(Device $device, User $contractor): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.workforce.devices.assign', $device), ['employee_id' => $contractor->id, 'assigned_date' => '2026-09-01'])
            ->assertSessionHasNoErrors();

        $this->post(route('super-admin.workforce.devices.lost', $device), ['notes' => 'Left in a taxi'])->assertSessionHasNoErrors();
    }

    private function advance(): void
    {
        $this->actingAs($this->superAdmin)->post(route('super-admin.payroll.advance', $this->period))->assertSessionHasNoErrors();
        $this->period->refresh();
    }

    public function test_a_lost_device_is_deducted_at_its_own_value(): void
    {
        $laptop = $this->addDevice('ThinkPad', 25000);
        $headset = $this->addDevice('Headset', 1250.50);

        $this->loseDevice($laptop, $this->contractor);
        $this->loseDevice($headset, $this->other);

        $this->assertSame('25000.00', DeviceDeduction::query()->whereHas('assignment', fn ($query) => $query->where('employee_id', $this->contractor->id))->sole()->amount);
        $this->assertSame(Device::STATUS_LOST, $laptop->refresh()->status);

        $this->get(route('super-admin.workforce.devices.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Workforce/Devices')
                ->where('counts.lost', 2)
                ->where('devices.1.name', 'ThinkPad')
                ->where('devices.1.lostBy', 'Christian Mae')
                ->where('devices.1.deducted', 25000));

        $this->advance(); // Process payroll

        $this->assertSame('25000.00', Payroll::query()->where('employee_id', $this->contractor->id)->sole()->device_deduction);
        $this->assertSame('1250.50', Payroll::query()->where('employee_id', $this->other->id)->sole()->device_deduction);
    }

    public function test_a_device_needs_a_value_and_must_be_assigned_to_be_lost(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.workforce.devices.store'), ['device_type' => 'Monitor', 'device_name' => 'Dell P2422H', 'value' => 0])
            ->assertSessionHasErrors('value');

        $this->post(route('super-admin.workforce.devices.store'), ['device_type' => 'Spaceship', 'device_name' => 'X', 'value' => 10])
            ->assertSessionHasErrors('device_type');

        $monitor = $this->addDevice('Monitor', 8000);

        $this->post(route('super-admin.workforce.devices.lost', $monitor))->assertSessionHasErrors('device');
        $this->assertDatabaseCount(DeviceDeduction::class, 0);
    }

    public function test_a_device_can_be_assigned_when_it_is_added(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.workforce.devices.store'), [
                'device_type' => 'Keyboard',
                'device_name' => 'Logitech K120',
                'value' => 650,
                'employee_id' => $this->contractor->id,
                'assigned_date' => '2026-09-26',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Device added and assigned.');

        $keyboard = Device::query()->sole();

        $this->assertSame(Device::STATUS_ASSIGNED, $keyboard->status);
        $this->assertSame('Keyboard', $keyboard->device_type);
        $this->assertTrue($keyboard->currentAssignment->employee->is($this->contractor));
    }

    public function test_a_returned_device_is_available_again(): void
    {
        $laptop = $this->addDevice('ThinkPad', 25000);
        $this->post(route('super-admin.workforce.devices.assign', $laptop), ['employee_id' => $this->contractor->id, 'assigned_date' => '2026-09-01']);

        $this->post(route('super-admin.workforce.devices.returned', $laptop))->assertSessionHasNoErrors();

        $this->assertSame(Device::STATUS_AVAILABLE, $laptop->refresh()->status);
        $this->assertDatabaseCount(DeviceDeduction::class, 0);
    }

    public function test_held_payroll_stays_out_of_the_release_until_the_hold_is_lifted(): void
    {
        Notification::fake();
        $this->loseDevice($this->addDevice('ThinkPad', 25000), $this->contractor);
        $this->advance(); // Process payroll

        $row = Payroll::query()->where('employee_id', $this->contractor->id)->sole();

        // Lost company equipment is suggested as a reason to hold; the Super Admin decides.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.payroll.show', $this->period))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canHold', true)
                ->where('rows.0.employee.name', 'Christian Mae')
                ->where('rows.0.holdSuggestion', 'Lost company equipment: ThinkPad')
                ->where('rows.1.holdSuggestion', null));

        $this->post(route('super-admin.payroll.hold', $row), ['hold_reason' => 'Lost company equipment: ThinkPad'])->assertSessionHasNoErrors();

        $this->advance(); // approved
        $this->advance(); // released

        $this->assertSame(PayrollStatus::Approved, $row->refresh()->status);
        $this->assertSame(PayrollStatus::Released, Payroll::query()->where('employee_id', $this->other->id)->sole()->status);
        Notification::assertNotSentTo($this->contractor, PayslipReleased::class);
        Notification::assertSentTo($this->other, PayslipReleased::class);
        $this->assertSame('on_hold', app(EmployeePayslips::class)->for($this->contractor)->first()['status']);

        $this->delete(route('super-admin.payroll.hold.release', $row))->assertSessionHasNoErrors();

        $this->assertSame(PayrollStatus::Released, $row->refresh()->status);
        $this->assertNull($row->held_at);
        Notification::assertSentTo($this->contractor, PayslipReleased::class);
        $this->assertSame('available', app(EmployeePayslips::class)->for($this->contractor)->first()['status']);
    }

    public function test_payroll_cannot_be_held_before_it_is_processed(): void
    {
        // Still in verification: there is nothing processed to hold yet.
        $row = Payroll::factory()->for($this->period, 'period')->for($this->contractor, 'employee')->create();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.hold', $row), ['hold_reason' => 'Unresolved checklist'])
            ->assertSessionHasErrors('hold_reason');

        $this->assertNull($row->refresh()->held_at);
    }
}
