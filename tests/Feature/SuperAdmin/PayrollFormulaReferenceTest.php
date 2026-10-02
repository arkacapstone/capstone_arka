<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AttendanceStatus;
use App\Enums\PayFrequency;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\User;
use App\Notifications\OvertimeDecided;
use App\Notifications\OvertimeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Payroll Formula Reference (2026-09-20), using its own worked example: Christian Mae,
 * ₱8,000 salary, 11 working days of 8 hours.
 */
class PayrollFormulaReferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $contractor;

    private Client $client;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 10:00:00'); // the day after the Sep 11 – 25 period ends
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->contractor = User::factory()->create(['name' => 'Christian Mae']);
        $this->client = Client::factory()->create(['client_name' => 'Aurora Dental']);

        Rate::factory()->for($this->contractor, 'employee')->for($this->client)->create([
            'gross_pay' => 8000,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'working_days' => 11,
            'hours_per_day' => 8,
            'effective_date' => '2026-01-01',
        ]);

        $this->period = PayrollPeriod::factory()->create(); // Sep 11 – Sep 25, semi-monthly, open
    }

    private function lock(): Payroll
    {
        foreach (['verification', 'locked'] as $step) {
            $this->actingAs($this->superAdmin)->post(route('super-admin.payroll.advance', $this->period))->assertSessionHasNoErrors();
        }

        return Payroll::query()->sole();
    }

    public function test_the_christian_mae_example_comes_out_exactly(): void
    {
        // Total Late & Undertime 15:46 (946 minutes) from attendance.
        Attendance::factory()->for($this->contractor, 'employee')->for($this->client)->status(AttendanceStatus::Late)
            ->create(['date' => '2026-09-15', 'late_minutes' => 600, 'undertime_minutes' => 0]);
        Attendance::factory()->for($this->contractor, 'employee')->for($this->client)->status(AttendanceStatus::Undertime)
            ->create(['date' => '2026-09-16', 'late_minutes' => 0, 'undertime_minutes' => 346]);

        $row = $this->lock();

        // Additional Hours (2nd client) 18:20 and half a day absent, entered in review like the timesheet.
        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.payroll.adjust', $row), [
                'additional_time' => '18:20',
                'days_absent' => 0.5,
                'cash_advance_deduction' => 0,
                'other_deductions' => 0,
            ])
            ->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertSame('90.9091', $row->hourly_rate);        // 8,000 ÷ (11 × 8)
        $this->assertSame('727.27', $row->daily_rate);          // 8,000 ÷ 11
        $this->assertSame('1666.67', $row->additional_pay);     // 18:20 = 18.333… h × hourly rate
        $this->assertSame('363.64', $row->absence_deduction);   // half a day × daily rate
        $this->assertSame('1433.33', $row->late_deduction);     // 15:46 = 15.766… h × hourly rate
        $this->assertSame('7869.70', $row->net_pay);            // 8,000 + 1,666.67 − 363.64 − 1,433.33
        $this->assertSame(1100, $row->additional_minutes);
        $this->assertSame(946, $row->late_minutes);
    }

    public function test_additional_hours_must_be_a_clock_time_and_absences_whole_or_half_days(): void
    {
        $row = $this->lock();

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.payroll.adjust', $row), ['additional_time' => '18:75', 'days_absent' => 0.3, 'cash_advance_deduction' => 0, 'other_deductions' => 0])
            ->assertSessionHasErrors(['additional_time', 'days_absent']);
    }

    public function test_overtime_is_a_ticket_paid_at_the_plain_hourly_rate_once_approved(): void
    {
        Notification::fake();

        // Present on all 11 working days (weekdays of Sep 11 – 25), so only overtime changes the pay.
        foreach (['11', '14', '15', '16', '17', '18', '21', '22', '23', '24', '25'] as $day) {
            Attendance::factory()->for($this->contractor, 'employee')->for($this->client)->create(['date' => "2026-09-{$day}"]);
        }

        // The contractor files the ticket, naming the client handler who approved it.
        $this->actingAs($this->contractor)
            ->post(route('employee.overtime.store'), [
                'client_id' => $this->client->id,
                'date' => '2026-09-20',
                'hours' => 2,
                'minutes' => 30,
                'client_handler' => 'Dr. Reyes',
                'reason' => 'Month-end patient recalls',
            ])
            ->assertSessionHasNoErrors();

        $ticket = OvertimeRequest::query()->sole();
        $this->assertSame(150, $ticket->minutes);
        $this->assertTrue($ticket->isPending());
        Notification::assertSentTo($this->superAdmin, OvertimeRequested::class);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.requests', ['type' => 'overtime']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingOvertime', 1)
                ->where('overtime.0.duration', '2:30')
                ->where('overtime.0.clientHandler', 'Dr. Reyes'));

        // Payroll is being reviewed when the Super Admin approves it: Hourly Rate × (150 ÷ 60), no premium.
        $row = $this->lock();
        $this->assertSame('0.00', $row->overtime_amount);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.requests.overtime.approve', $ticket))
            ->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertSame('227.27', $row->overtime_amount);
        $this->assertSame('8227.27', $row->net_pay);
        $this->assertSame($row->id, $ticket->fresh()->payroll_id);
        Notification::assertSentTo($this->contractor, OvertimeDecided::class);

        // Recalculating pays it once, not twice.
        $this->actingAs($this->superAdmin)->post(route('super-admin.payroll.revert', $this->period));
        $this->actingAs($this->superAdmin)->post(route('super-admin.payroll.advance', $this->period));
        $this->assertSame('227.27', Payroll::query()->sole()->overtime_amount);
    }

    public function test_overtime_approved_before_payroll_is_paid_in_the_next_payroll(): void
    {
        $ticket = OvertimeRequest::create([
            'employee_id' => $this->contractor->id,
            'client_id' => $this->client->id,
            'date' => '2026-09-18',
            'minutes' => 60,
            'client_handler' => 'Dr. Reyes',
            'reason' => 'Clinic event',
            'status' => OvertimeRequest::STATUS_APPROVED,
            'amount' => 200,
        ]);

        $row = $this->lock();

        $this->assertSame('200.00', $row->overtime_amount);
        $this->assertSame($row->id, $ticket->fresh()->payroll_id);
    }

    public function test_overtime_can_only_be_filed_for_the_contractors_own_clients_and_rejections_pay_nothing(): void
    {
        $this->actingAs($this->contractor)
            ->post(route('employee.overtime.store'), [
                'client_id' => Client::factory()->create()->id,
                'date' => '2026-09-20',
                'hours' => 1,
                'minutes' => 0,
                'client_handler' => 'Someone',
                'reason' => 'x',
            ])
            ->assertSessionHasErrors('client_id');

        $this->actingAs($this->contractor)->post(route('employee.overtime.store'), [
            'client_id' => $this->client->id, 'date' => '2026-09-20', 'hours' => 1, 'minutes' => 0, 'client_handler' => 'Dr. Reyes', 'reason' => 'Extra calls',
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.requests.overtime.reject', OvertimeRequest::query()->sole()), ['note' => 'Not approved by the clinic.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $this->lock()->overtime_amount);
    }

    public function test_the_contractor_sees_overtime_in_the_sidebar_and_can_withdraw_a_waiting_ticket(): void
    {
        $this->actingAs($this->contractor)->post(route('employee.overtime.store'), [
            'client_id' => $this->client->id, 'date' => '2026-09-20', 'hours' => 0, 'minutes' => 45, 'client_handler' => 'Dr. Reyes', 'reason' => 'Late call',
        ]);

        $this->actingAs($this->contractor)
            ->get(route('employee.overtime.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Overtime')
                ->where('tickets.0.duration', '0:45')
                ->where('navigation', fn ($items) => collect($items)->contains('key', 'overtime')));

        $this->actingAs($this->contractor)
            ->post(route('employee.overtime.cancel', OvertimeRequest::query()->sole()))
            ->assertSessionHasNoErrors();

        $this->assertSame(OvertimeRequest::STATUS_CANCELLED, OvertimeRequest::query()->sole()->status);
    }
}
