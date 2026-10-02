<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AttendanceStatus;
use App\Enums\CashAdvanceStatus;
use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Client;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\Reward;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payslips\EmployeePayslips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Payroll Formula & Scenario Reference, section 3: Persons A–F, worked through attendance,
 * payroll and the payslip. Baseline: 10 working days per period, 8 hours a day Full-Time and
 * 4 hours a day Part-Time; no schedules, so every working day without attendance is an absence.
 */
class PayrollScenarioReferenceTest extends TestCase
{
    use RefreshDatabase;

    /** Ten weekdays inside the Sep 11 – 25 period. */
    private const DAYS = ['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'];

    private PayrollPeriod $period;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-09-26 10:00:00');
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->period = PayrollPeriod::factory()->create(); // Sep 11 – 25, semi-monthly
    }

    private function client(User $contractor, string $name, EmploymentType $type, float $gross): Client
    {
        $client = Client::factory()->create(['client_name' => $name]);

        Rate::factory()->for($contractor, 'employee')->for($client)->create([
            'employment_type' => $type,
            'gross_pay' => $gross,
            'pay_frequency' => PayFrequency::SemiMonthly,
            'working_days' => 10,
            'hours_per_day' => $type === EmploymentType::FullTime ? 8 : 4,
            'effective_date' => '2026-01-01',
        ]);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $firstDay  extra attributes for the first day (e.g. lateness)
     */
    private function worked(User $contractor, Client $client, int $days, float $hoursPerDay, array $firstDay = []): void
    {
        foreach (array_slice(self::DAYS, 0, $days) as $index => $date) {
            Attendance::factory()->for($contractor, 'employee')->for($client)->create([
                'date' => $date,
                'actual_hours' => $hoursPerDay,
                ...($index === 0 ? $firstDay : []),
            ]);
        }
    }

    private function approvedOvertime(User $contractor, Client $client, int $minutes): void
    {
        $ticket = OvertimeRequest::create([
            'employee_id' => $contractor->id,
            'client_id' => $client->id,
            'date' => '2026-09-19',
            'minutes' => $minutes,
            'client_handler' => 'Client handler',
            'reason' => 'Approved extra time',
            'status' => OvertimeRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->superAdmin)->post(route('super-admin.requests.overtime.approve', $ticket))->assertSessionHasNoErrors();
    }

    private function cashAdvance(User $contractor, float $amount): void
    {
        CashAdvance::factory()->for($contractor, 'employee')->create([
            'amount' => $amount,
            'remaining_balance' => $amount,
            'status' => CashAdvanceStatus::Approved,
            'released_date' => '2026-09-20',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payslip(User $contractor): array
    {
        app(PayrollCalculator::class)->calculate($this->period);

        return app(EmployeePayslips::class)->present(
            $this->period->payrolls()->where('employee_id', $contractor->id)->with(['period', 'client', 'rate'])->get(),
            preview: true,
        );
    }

    /**
     * @param  list<array{label: string, amount: float}>  $lines
     * @return array<string, float>
     */
    private function lines(array $lines): array
    {
        return collect($lines)->mapWithKeys(fn (array $line) => [$line['label'] => $line['amount']])->all();
    }

    public function test_person_a_full_time_and_part_time_both_worked_exactly_as_expected(): void
    {
        $contractor = User::factory()->create();
        $fullTime = $this->client($contractor, 'Aurora Dental', EmploymentType::FullTime, 8000);
        $partTime = $this->client($contractor, 'Northline', EmploymentType::PartTime, 3200);
        $this->worked($contractor, $fullTime, 10, 8);
        $this->worked($contractor, $partTime, 10, 4);

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Gross Pay (Aurora Dental · Full-Time)' => 8000.0,
            'Additional Pay (Northline · Part-Time)' => 3200.0,
        ], $this->lines($payslip['earnings']));
        $this->assertSame(11200.0, $payslip['grossTotal']);
        $this->assertSame([], $payslip['deductions']);
        $this->assertSame(11200.0, $payslip['net']);
    }

    public function test_person_b_eight_extra_part_time_hours_covering_a_shift(): void
    {
        $contractor = User::factory()->create();
        $fullTime = $this->client($contractor, 'Aurora Dental', EmploymentType::FullTime, 8000);
        $partTime = $this->client($contractor, 'Northline', EmploymentType::PartTime, 3200);
        $this->worked($contractor, $fullTime, 10, 8);
        $this->worked($contractor, $partTime, 10, 4.8); // 48 hours against 40 expected

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Gross Pay (Aurora Dental · Full-Time)' => 8000.0,
            'Additional Pay (Northline · Part-Time)' => 3200.0,
            'Additional Hours Pay (Northline)' => 640.0, // 8 h × ₱80.00, the rate itself unchanged
        ], $this->lines($payslip['earnings']));
        $this->assertSame('8 hrs @ ₱80.00/hr', $payslip['earnings'][2]['detail']);
        $this->assertSame(11840.0, $payslip['net']);
    }

    public function test_extra_hours_that_were_approved_as_overtime_are_paid_only_once(): void
    {
        $contractor = User::factory()->create();
        $partTime = $this->client($contractor, 'Northline', EmploymentType::PartTime, 3200);
        $this->worked($contractor, $partTime, 10, 4.8); // 48 hours against 40 expected
        $this->approvedOvertime($contractor, $partTime, 120); // 2 of those 8 hours were approved overtime

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Gross Pay (Northline · Part-Time)' => 3200.0,
            'Additional Hours Pay (Northline)' => 480.0, // the other 6 h × ₱80.00
            'Overtime Pay (Northline)' => 160.0,         // 2 h × ₱80.00
        ], $this->lines($payslip['earnings']));
        $this->assertSame(3840.0, $payslip['net']); // 48 hours paid once: 3,200 + 8 × 80
    }

    public function test_extra_full_time_hours_are_not_paid_without_an_overtime_ticket(): void
    {
        $contractor = User::factory()->create();
        $fullTime = $this->client($contractor, 'Aurora Dental', EmploymentType::FullTime, 8000);
        $this->worked($contractor, $fullTime, 10, 9); // 90 hours against 80 expected

        $payslip = $this->payslip($contractor);

        $this->assertSame(['Gross Pay (Aurora Dental · Full-Time)' => 8000.0], $this->lines($payslip['earnings']));
        $this->assertSame(8000.0, $payslip['net']);
    }

    public function test_a_reward_with_an_amount_is_additional_pay_on_the_next_payslip_only(): void
    {
        $contractor = User::factory()->create();
        $fullTime = $this->client($contractor, 'Aurora Dental', EmploymentType::FullTime, 8000);
        $this->worked($contractor, $fullTime, 10, 8);
        Reward::create(['employee_id' => $contractor->id, 'reward_type' => 'Bonus', 'amount' => 500, 'description' => 'Top KPI for September', 'awarded_by' => $this->superAdmin->id, 'awarded_at' => '2026-09-22 10:00:00']);
        Reward::create(['employee_id' => $contractor->id, 'reward_type' => 'Recognition', 'amount' => null, 'awarded_by' => $this->superAdmin->id, 'awarded_at' => '2026-09-22 10:00:00']);

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Gross Pay (Aurora Dental · Full-Time)' => 8000.0,
            'Additional Pay (Reward · Bonus)' => 500.0,
        ], $this->lines($payslip['earnings']));
        $this->assertSame('Top KPI for September', $payslip['earnings'][1]['detail']);
        $this->assertSame(8500.0, $payslip['net']);

        // Recalculating the same period pays it once, not twice.
        $this->assertSame(8500.0, $this->payslip($contractor)['net']);
    }

    public function test_person_c_part_time_only_late_fifteen_minutes(): void
    {
        $contractor = User::factory()->create();
        $partTime = $this->client($contractor, 'Northline', EmploymentType::PartTime, 4400);
        $this->worked($contractor, $partTime, 10, 4, ['status' => AttendanceStatus::Late, 'late_minutes' => 15]);

        $payslip = $this->payslip($contractor);

        $this->assertSame(['Gross Pay (Northline · Part-Time)' => 4400.0], $this->lines($payslip['earnings']));
        $this->assertSame(['Late / Undertime' => 27.5], $this->lines($payslip['deductions'])); // 0.25 h × ₱110.00
        $this->assertSame(4372.5, $payslip['net']);
    }

    public function test_person_d_two_part_time_clients_with_one_hour_overtime_on_the_first(): void
    {
        $contractor = User::factory()->create();
        $first = $this->client($contractor, 'Harbor Vet', EmploymentType::PartTime, 3600);
        $second = $this->client($contractor, 'Northline', EmploymentType::PartTime, 3000);
        $this->worked($contractor, $first, 10, 4);
        $this->worked($contractor, $second, 10, 4);
        $this->approvedOvertime($contractor, $first, 60);

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Gross Pay (Harbor Vet · Part-Time)' => 3600.0,
            'Additional Pay (Northline · Part-Time)' => 3000.0,
            'Overtime Pay (Harbor Vet)' => 90.0, // 1 h × ₱90.00, no premium
        ], $this->lines($payslip['earnings']));
        $this->assertSame(6690.0, $payslip['net']);
    }

    public function test_person_e_full_time_thirty_minutes_undertime_and_a_five_hundred_cash_advance(): void
    {
        $contractor = User::factory()->create();
        $fullTime = $this->client($contractor, 'Aurora Dental', EmploymentType::FullTime, 7000);
        $this->worked($contractor, $fullTime, 10, 8, ['status' => AttendanceStatus::Undertime, 'undertime_minutes' => 30]);
        $this->cashAdvance($contractor, 500);

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Late / Undertime' => 43.75, // 0.5 h × ₱87.50
            'Cash Advance Repayment' => 500.0,
        ], $this->lines($payslip['deductions']));
        $this->assertSame(543.75, $payslip['deductionsTotal']);
        $this->assertSame(6456.25, $payslip['net']);
    }

    public function test_person_f_extra_part_time_hours_overtime_absences_lateness_and_a_cash_advance(): void
    {
        $contractor = User::factory()->create();
        $fullTime = $this->client($contractor, 'Aurora Dental', EmploymentType::FullTime, 8000);
        $partTime = $this->client($contractor, 'Northline', EmploymentType::PartTime, 3200);
        $this->worked($contractor, $fullTime, 8, 8, ['status' => AttendanceStatus::Late, 'late_minutes' => 5]); // absent 2 days
        $this->worked($contractor, $partTime, 10, 5.6); // 56 hours against 40 expected
        $this->approvedOvertime($contractor, $fullTime, 90);
        $this->cashAdvance($contractor, 1000);

        $payslip = $this->payslip($contractor);

        $this->assertSame([
            'Gross Pay (Aurora Dental · Full-Time)' => 8000.0,
            'Additional Pay (Northline · Part-Time)' => 3200.0,
            'Additional Hours Pay (Northline)' => 1280.0, // 16 h × ₱80.00
            'Overtime Pay (Aurora Dental)' => 150.0,      // 1.5 h × ₱100.00
        ], $this->lines($payslip['earnings']));
        $this->assertSame(12630.0, $payslip['grossTotal']);

        $this->assertSame([
            'Absences' => 1600.0,          // 2 days × ₱800.00
            'Late / Undertime' => 8.33,    // 5 min × ₱100.00/h
            'Cash Advance Repayment' => 1000.0,
        ], $this->lines($payslip['deductions']));
        $this->assertSame('2 days', $payslip['deductions'][0]['detail']);
        $this->assertSame(2608.33, $payslip['deductionsTotal']);

        $this->assertSame(10021.67, $payslip['net']);
    }
}
