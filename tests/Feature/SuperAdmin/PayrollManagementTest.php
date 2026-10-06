<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AttendanceStatus;
use App\Enums\CashAdvanceStatus;
use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Client;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AttendanceLockedForPayroll;
use App\Notifications\PayrollVerificationOpened;
use App\Notifications\PayslipReleased;
use App\Services\Attendance\AttendanceLock;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payslips\EmployeePayslips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $employee;

    private Client $client;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 10:00:00'); // the day after the Sep 11 – 25 period ends
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->employee = User::factory()->create(['name' => 'Christian Mae']);
        $this->client = Client::factory()->create();

        // The Payroll Formula Reference example: ₱8,000 over 11 working days of 8 hours.
        Rate::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'gross_pay' => 8000,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'working_days' => 11,
            'hours_per_day' => 8,
            'effective_date' => '2026-01-01',
        ]);

        $this->period = PayrollPeriod::factory()->create(); // Sep 11 – Sep 25, semi-monthly, open
    }

    private function advance(): void
    {
        // Payroll is processed only after the Admin submits the verified period.
        if ($this->period->status === PayrollPeriodStatus::Verification) {
            $this->period->update(['admin_submitted_at' => now()]);
        }

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.advance', $this->period))
            ->assertSessionHasNoErrors();

        $this->period->refresh();
    }

    /**
     * Present on each of the 11 working days (weekdays of Sep 11 – 25), except the given dates.
     *
     * @param  list<string>  $except
     */
    private function worked(array $except = []): void
    {
        foreach (['11', '14', '15', '16', '17', '18', '21', '22', '23', '24', '25'] as $day) {
            if (! in_array("2026-09-{$day}", $except, true)) {
                Attendance::factory()->for($this->employee, 'employee')->for($this->client)->create(['date' => "2026-09-{$day}"]);
            }
        }
    }

    public function test_the_payroll_module_and_period_pages_open(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.payroll'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Payroll/Index')->has('periods', 1)->where('current.action.label', 'Open attendance verification'));

        $this->get(route('super-admin.payroll.show', $this->period))
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Payroll/Show')->where('employeesPaid', 1));
    }

    public function test_the_overview_shows_the_chosen_pay_frequency(): void
    {
        $this->actingAs($this->superAdmin);

        // Semi-monthly by default: on Sep 26 that is the Sep 26 – Oct 10 period, not created yet.
        $this->get(route('super-admin.payroll'))
            ->assertInertia(fn (Assert $page) => $page->where('frequency', 'semi_monthly')->where('current.period.frequency', 'Semi-monthly')->where('current.period.startDate', '2026-09-26'));

        // No weekly period yet.
        $this->get(route('super-admin.payroll', ['frequency' => 'weekly']))
            ->assertInertia(fn (Assert $page) => $page->where('frequency', 'weekly')->where('current', null));

        $weekly = PayrollPeriod::factory()->create([
            'period_name' => 'Sep 21 – Sep 25, 2026',
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-25',
            'cutoff_date' => '2026-09-25',
            'release_date' => '2026-09-28',
            'pay_frequency' => PayFrequency::Weekly,
        ]);

        $this->get(route('super-admin.payroll', ['frequency' => 'weekly']))
            ->assertInertia(fn (Assert $page) => $page->where('current.period.id', $weekly->id)->where('current.period.frequency', 'Weekly'));

        // The semi-monthly overview is unchanged by the weekly period.
        $this->get(route('super-admin.payroll'))
            ->assertInertia(fn (Assert $page) => $page->where('current.period.frequency', 'Semi-monthly')->where('current.period.startDate', '2026-09-26'));
    }

    public function test_periods_are_weekly_semi_monthly_or_monthly_never_hourly(): void
    {
        // The periods list carries each pay frequency for the filter; New period offers only these three.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.payroll', ['tab' => 'periods']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('periods.0.frequencyValue', 'semi_monthly')
                ->where('frequencies', [
                    ['value' => 'weekly', 'label' => 'Weekly'],
                    ['value' => 'semi_monthly', 'label' => 'Semi-monthly'],
                    ['value' => 'monthly', 'label' => 'Monthly'],
                ]));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.store'), [
                'period_name' => 'Hourly week',
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-09',
                'cutoff_date' => '2026-10-09',
                'release_date' => '2026-10-12',
                'pay_frequency' => 'hourly',
            ])
            ->assertSessionHasErrors('pay_frequency');
    }

    public function test_opening_verification_notifies_everyone_paid_in_the_period(): void
    {
        Notification::fake();
        $unpaid = User::factory()->create();

        $this->advance();

        $this->assertSame(PayrollPeriodStatus::Verification, $this->period->status);
        Notification::assertSentTo($this->employee, PayrollVerificationOpened::class);
        Notification::assertNotSentTo($unpaid, PayrollVerificationOpened::class);
    }

    public function test_locking_calculates_payroll_with_the_formula_reference(): void
    {
        Notification::fake();

        Attendance::factory()->for($this->employee, 'employee')->for($this->client)->status(AttendanceStatus::Absent)->create(['date' => '2026-09-15']);
        Attendance::factory()->for($this->employee, 'employee')->for($this->client)->status(AttendanceStatus::Late)->create(['date' => '2026-09-16', 'late_minutes' => 60, 'undertime_minutes' => 30]);
        Attendance::factory()->for($this->employee, 'employee')->for($this->client)->status(AttendanceStatus::Absent)->create(['date' => '2026-09-30']); // next period
        $this->worked(except: ['2026-09-15', '2026-09-16']);

        $this->advance(); // verification
        $this->advance(); // locked

        $this->assertSame(PayrollPeriodStatus::Locked, $this->period->status);
        $this->assertTrue(app(AttendanceLock::class)->isLocked($this->period->start_date));
        Notification::assertSentTo($this->employee, AttendanceLockedForPayroll::class);

        $row = Payroll::sole();
        $this->assertSame('90.9091', $row->hourly_rate);  // 8000 ÷ (11 × 8)
        $this->assertSame('727.27', $row->daily_rate);    // 8000 ÷ 11
        $this->assertSame('727.27', $row->absence_deduction); // 1 day absent
        $this->assertSame('136.36', $row->late_deduction);    // 1.5 h late/undertime
        $this->assertSame('7136.37', $row->net_pay);
        $this->assertSame(PayrollStatus::Draft, $row->status);
    }

    public function test_review_adjustments_recompute_net_pay_and_survive_recalculation(): void
    {
        $this->advance();
        $this->advance();
        $row = Payroll::sole();

        $this->actingAs($this->superAdmin)
            ->patch(route('super-admin.payroll.adjust', $row), [
                'additional_time' => '2:00',
                'days_absent' => 0.5,
                'cash_advance_deduction' => 5000, // no longer adjustable: ignored
                'other_deductions' => 1000,
            ])
            ->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertSame('181.82', $row->additional_pay);    // 2 h × hourly rate
        $this->assertSame('363.64', $row->absence_deduction); // half a day × daily rate
        $this->assertSame('0.00', $row->cash_advance_deduction);
        $this->assertSame('6818.18', $row->net_pay);          // 8000 + 181.82 − 363.64 − 1000
        $this->assertSame(PayrollStatus::Reviewed, $row->status);

        // Recalculating keeps the reviewed half day and additional hours.
        app(PayrollCalculator::class)->calculate($this->period);
        $this->assertSame('6818.18', $row->fresh()->net_pay);

        // The payslip shows the extra hours as Additional Hours Pay at the hourly rate.
        $payslip = app(EmployeePayslips::class)->present($row->fresh()->period->payrolls()->with(['period', 'client', 'rate'])->get(), preview: true);
        $this->assertSame('Additional Hours Pay ('.$row->client->client_name.')', $payslip['earnings'][1]['label']);
        $this->assertSame(181.82, $payslip['earnings'][1]['amount']);
        $this->assertSame('2 hrs @ ₱90.91/hr', $payslip['earnings'][1]['detail']);
    }

    public function test_unlocking_reopens_attendance_clears_the_draft_and_tells_employees(): void
    {
        Notification::fake();
        $this->advance();
        $this->advance();

        $this->actingAs($this->superAdmin)->post(route('super-admin.payroll.revert', $this->period))->assertSessionHasNoErrors();

        $this->assertSame(PayrollPeriodStatus::Verification, $this->period->fresh()->status);
        $this->assertSame(0, Payroll::count());
        $this->assertFalse(app(AttendanceLock::class)->isLocked($this->period->start_date));
        Notification::assertSentTo($this->employee, PayrollVerificationOpened::class, fn ($notification) => $notification->toArray($this->employee)['title'] === 'Attendance reopened for review');
    }

    public function test_a_rate_change_during_the_period_is_one_row_paid_at_the_latest_rate(): void
    {
        // The rate for this client changes on Sep 18: the old one ends the day before.
        Rate::query()->where('employee_id', $this->employee->id)->update(['end_date' => '2026-09-17']);
        $newRate = Rate::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'gross_pay' => 8800,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'working_days' => 11,
            'hours_per_day' => 8,
            'effective_date' => '2026-09-18',
        ]);
        $this->worked();

        $this->advance(); // verification
        $this->advance(); // locked: payroll calculated

        $row = Payroll::query()->sole();
        $this->assertTrue($row->rate->is($newRate));
        $this->assertSame('8800.00', $row->gross_pay);
        // Attendance over the whole period counts, not only the days after the change.
        $this->assertSame('0.0', $row->days_absent);
    }

    public function test_the_overview_shows_the_latest_payroll_while_the_current_period_has_none(): void
    {
        $this->worked();
        $this->advance();
        $this->advance(); // Sep 11 – 25 has payroll; today (Sep 26) falls in the next, not yet created period.

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.payroll'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('current.period.id', $this->period->id)
                ->where('current.records.total', 1)
                ->where('upcoming.period.isProjected', true)
                ->where('upcoming.period.startDate', '2026-09-26')
                ->where('upcoming.action.label', 'Open attendance verification'));
    }

    public function test_absences_never_exceed_the_rates_working_days(): void
    {
        // A seven-day schedule in the period, nothing worked: still at most the rate's 11 working days.
        Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'start_date' => '2026-09-01',
        ]);

        $this->advance();
        $this->advance();

        $row = Payroll::sole();
        $this->assertSame('11.0', $row->days_absent);
        $this->assertSame('8000.00', $row->absence_deduction);
    }

    public function test_net_pay_never_goes_below_zero_and_the_shortfall_is_flagged(): void
    {
        // Absent every day and late on top of it.
        Attendance::factory()->for($this->employee, 'employee')->for($this->client)->create(['date' => '2026-09-14', 'status' => AttendanceStatus::Absent, 'late_minutes' => 120]);

        $this->advance();
        $this->advance();

        $row = Payroll::sole();
        $this->assertSame('0.00', $row->net_pay);
        $this->assertSame(181.82, PayrollCalculator::shortfall($row)); // 120 minutes × 8,000 ÷ 88 hours

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.payroll.show', $this->period))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.net', 0)->where('rows.0.shortfall', 181.82));
    }

    public function test_approving_and_releasing_sends_every_employee_their_payslip(): void
    {
        Notification::fake();
        $this->worked();
        $this->advance();
        $this->advance();
        $this->advance(); // approved

        $this->assertSame(PayrollPeriodStatus::Processed, $this->period->status);
        $this->assertSame(PayrollStatus::Approved, Payroll::sole()->status);

        // Approved payroll can no longer be adjusted or unlocked.
        $this->actingAs($this->superAdmin)->patch(route('super-admin.payroll.adjust', Payroll::sole()), ['additional_time' => '1:00', 'days_absent' => 0, 'cash_advance_deduction' => 0, 'other_deductions' => 0])->assertSessionHasErrors('status');
        $this->post(route('super-admin.payroll.revert', $this->period))->assertSessionHasErrors('status');

        $this->advance(); // released

        $this->assertSame(PayrollStatus::Released, Payroll::sole()->status);
        Notification::assertSentTo($this->employee, PayslipReleased::class);

        $this->actingAs($this->employee)
            ->get(route('employee.payslips.index'))
            ->assertInertia(fn (Assert $page) => $page->where('payslips.0.status', 'available')->where('payslips.0.net', 8000));
    }

    public function test_an_approved_cash_advance_is_deducted_from_the_employees_payroll_automatically(): void
    {
        Notification::fake();
        $this->worked();
        $advance = CashAdvance::factory()->for($this->employee, 'employee')->create(['amount' => 1500, 'remaining_balance' => 1500]);

        // The employee filed it; the Super Admin only approves.
        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.approve', $advance))->assertSessionHasNoErrors();

        $this->advance(); // verification
        $this->advance(); // locked: payroll calculated

        $this->assertSame('1500.00', Payroll::sole()->cash_advance_deduction); // the full amount, all at once
        $this->assertSame('6500.00', Payroll::sole()->net_pay);

        $this->advance(); // approved
        $this->advance(); // released: the deduction becomes a repayment

        $advance->refresh();
        $this->assertEquals(0, (float) $advance->remaining_balance);
        $this->assertSame(CashAdvanceStatus::Repaid, $advance->status);
        $this->assertNotNull($advance->repayments()->sole()->payroll_id);
    }

    /**
     * The contractor asks for a cash advance on the given day (the Sep 11 – 25 period pays on Sep 30).
     */
    private function requestCashAdvance(float $amount, string $on = '2026-09-20 10:00:00'): TestResponse
    {
        $this->travelTo($on);
        $response = $this->actingAs($this->employee)->post(route('employee.cash-advances.store'), ['amount' => $amount, 'reason' => 'Rent']);
        $this->travelTo('2026-09-26 10:00:00');

        return $response;
    }

    public function test_a_cash_advance_is_requested_during_the_period_up_to_the_gross_pay_and_repaid_on_its_payday(): void
    {
        Notification::fake();
        $this->worked();

        // Any day of the Sep 11 – 25 period, up to the ₱8,000 gross pay.
        $this->requestCashAdvance(8000.01)->assertSessionHasErrors('amount');
        $this->requestCashAdvance(5000)->assertSessionHasNoErrors();
        $advance = CashAdvance::sole();
        $this->assertSame('2026-09-30', $advance->payday->toDateString());
        $this->assertSame('8000.00', $advance->gross_pay);

        $this->advance(); // verification
        $this->advance(); // locked: payroll calculated, before the advance is approved
        $this->assertSame('0.00', Payroll::sole()->cash_advance_deduction);

        // The Super Admin releases less than was asked; it goes onto the calculated payroll right away.
        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.approve', $advance), ['amount' => 5000.01])->assertSessionHasErrors('amount');
        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.approve', $advance), ['amount' => 3000])->assertSessionHasNoErrors();
        $this->assertSame('3000.00', $advance->fresh()->amount);
        $this->assertSame('5000.00', $advance->fresh()->requested_amount);
        $this->assertSame('3000.00', Payroll::sole()->cash_advance_deduction);
        $this->assertSame('5000.00', Payroll::sole()->net_pay);

        // One a month: the next pay period (from Sep 26) is still September.
        $this->requestCashAdvance(1000, '2026-09-27 10:00:00')->assertSessionHasErrors('amount');

        $this->advance(); // approved
        $this->advance(); // released: repaid in full on this payday
        $this->assertSame(CashAdvanceStatus::Repaid, $advance->fresh()->status);
    }

    public function test_a_cash_advance_after_the_cutoff_is_repaid_on_the_next_payday_even_before_that_period_is_created(): void
    {
        $this->worked();

        // Sep 26 belongs to the Sep 26 – Oct 10 period, paid on Oct 15. It has not been created yet.
        $this->requestCashAdvance(1000, '2026-09-26 10:00:00')->assertSessionHasNoErrors();
        $advance = CashAdvance::sole();
        $this->assertSame('2026-10-15', $advance->payday->toDateString());
        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.approve', $advance))->assertSessionHasNoErrors();

        $this->advance(); // verification
        $this->advance(); // locked: the Sep 30 payroll leaves it for Oct 15
        $this->assertSame('0.00', Payroll::sole()->cash_advance_deduction);
    }

    public function test_cash_advances_cannot_be_requested_without_pay_in_the_period(): void
    {
        $this->travelTo('2026-09-20 10:00:00');
        $contractor = User::factory()->create(); // no client rate

        $this->actingAs($contractor)
            ->post(route('employee.cash-advances.store'), ['amount' => 1000, 'reason' => 'Rent'])
            ->assertSessionHasErrors(['amount' => 'You have no pay in this pay period to repay a cash advance from.']);

        $this->actingAs($contractor)
            ->get(route('employee.cash-advances.index'))
            ->assertInertia(fn (Assert $page) => $page->where('requestWindow.open', false));
    }

    public function test_a_cash_advance_cannot_be_approved_after_that_payroll_is_approved(): void
    {
        $this->worked();
        $this->requestCashAdvance(1000)->assertSessionHasNoErrors();

        $this->advance(); // verification
        $this->advance(); // locked
        $this->advance(); // approved

        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.approve', CashAdvance::sole()))->assertSessionHasErrors('status');
        $this->assertSame(CashAdvanceStatus::Pending, CashAdvance::sole()->status);
    }

    public function test_only_what_the_pay_covers_is_recorded_as_repaid(): void
    {
        Notification::fake();
        $this->worked(['2026-09-11', '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18']); // 6 days absent
        $this->requestCashAdvance(8000)->assertSessionHasNoErrors();
        $advance = CashAdvance::sole();
        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.approve', $advance))->assertSessionHasNoErrors();

        $this->advance(); // verification
        $this->advance(); // locked
        $this->assertSame('0.00', Payroll::sole()->net_pay); // 8000 − 4363.64 absences − 8000 advance, never below zero

        $this->advance(); // approved
        $this->advance(); // released

        // Only the ₱3,636.36 the pay covered was withheld; the rest is still owed.
        $this->assertSame('4363.64', $advance->fresh()->remaining_balance);
        $this->assertSame(CashAdvanceStatus::Approved, $advance->fresh()->status);
    }

    public function test_the_dashboard_can_create_the_projected_period_and_open_verification_at_once(): void
    {
        Notification::fake();
        $this->period->delete();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.store'), [
                'start_date' => '2026-09-11',
                'end_date' => '2026-09-25',
                'cutoff_date' => '2026-09-25',
                'release_date' => '2026-09-30',
                'pay_frequency' => 'semi_monthly',
                'open_verification' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(PayrollPeriodStatus::Verification, PayrollPeriod::sole()->status);
        Notification::assertSentTo($this->employee, PayrollVerificationOpened::class);
    }

    public function test_an_unsaved_projected_period_only_offers_to_open_verification(): void
    {
        $this->period->delete(); // today (Sep 25) is the projected cutoff day

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.period.isProjected', true)
                ->where('payroll.action.label', 'Open attendance verification')
                ->where('payroll.action.revertLabel', null)
            );
    }

    public function test_periods_of_the_same_frequency_cannot_overlap(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.store'), [
                'start_date' => '2026-09-20',
                'end_date' => '2026-10-05',
                'cutoff_date' => '2026-10-05',
                'release_date' => '2026-10-10',
                'pay_frequency' => 'semi_monthly',
            ])
            ->assertSessionHasErrors('start_date');
    }

    public function test_admins_and_employees_cannot_reach_payroll(): void
    {
        foreach ([User::factory()->admin()->create(), $this->employee] as $user) {
            $this->actingAs($user)->get(route('super-admin.payroll'))->assertForbidden();
            $this->actingAs($user)->post(route('super-admin.payroll.advance', $this->period))->assertForbidden();
        }
    }

    public function test_missing_days_are_deducted_so_a_contractor_who_leaves_mid_period_is_paid_for_days_worked(): void
    {
        // Worked the first 5 working days (Sep 11 – 17), then resigned and was deactivated.
        $this->worked(except: ['2026-09-18', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25']);
        $this->employee->update(['status' => 'inactive']);

        $this->advance(); // verification
        $this->advance(); // locked

        $row = Payroll::sole();
        $this->assertSame('6.0', $row->days_absent);           // 11 working days − 5 worked
        $this->assertSame('4363.64', $row->absence_deduction); // 6 × 727.27
        $this->assertSame('3636.36', $row->net_pay);           // 5 days × 727.27
    }

    public function test_absences_follow_the_schedule_so_part_time_days_are_not_missed_days(): void
    {
        // Mondays and Saturdays only: Sep 12, 14, 19 and 21 fall in the period. Missed Sep 21.
        Schedule::factory()->for($this->employee, 'employee')->for($this->client)->create([
            'job_position' => 'Contractor',
            'working_days' => ['mon', 'sat'],
            'start_date' => '2026-09-01',
        ]);

        foreach (['2026-09-12', '2026-09-14', '2026-09-19'] as $date) {
            Attendance::factory()->for($this->employee, 'employee')->for($this->client)->create(['date' => $date]);
        }

        $this->advance(); // verification
        $this->advance(); // locked

        $this->assertSame('1.0', Payroll::sole()->days_absent);
    }

    public function test_verification_opens_any_time_during_the_period_but_only_once(): void
    {
        $this->travelTo('2026-09-15 09:00:00'); // mid-period

        $this->advance();
        $this->assertSame(PayrollPeriodStatus::Verification, $this->period->status);

        // It cannot be taken back to open and opened again.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.revert', $this->period))
            ->assertSessionHasErrors('status');

        $this->assertSame(PayrollPeriodStatus::Verification, $this->period->fresh()->status);
    }

    public function test_creating_a_period_that_leaves_a_gap_warns_the_super_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.store'), [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-15',
                'cutoff_date' => '2026-11-15',
                'release_date' => '2026-11-20',
                'pay_frequency' => 'semi_monthly',
            ])
            ->assertSessionHas('warning', fn ($warning) => str_contains($warning, 'Sep 26 – Oct 31, 2026'));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.store'), [
                'start_date' => '2026-09-26',
                'end_date' => '2026-10-10',
                'cutoff_date' => '2026-10-10',
                'release_date' => '2026-10-15',
                'pay_frequency' => 'semi_monthly',
            ])
            ->assertSessionMissing('warning');
    }
}
