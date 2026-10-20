<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRepayment;
use App\Models\ClientAssignment;
use App\Models\DeviceDeduction;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollOvertime;
use App\Models\PayrollPeriod;
use App\Models\PayrollReward;
use App\Models\Rate;
use App\Models\Reward;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Draft payroll for a period, one row per client assignment (Payroll Formula & Scenario Reference):
 *
 *   Hourly Rate           = Gross Pay ÷ (Days per Period × Hours per Day)
 *   Daily Rate            = Gross Pay ÷ Days per Period
 *   Additional Hours Pay  = Hourly Rate × hours worked beyond that Part-Time client's expected hours
 *   Overtime Pay          = the approved overtime tickets' amounts, no premium
 *   Net Pay               = Gross Pay + Additional Hours Pay + Overtime Pay + Rewards
 *                           − (Daily Rate × Days Absent) − (Hourly Rate × Late/Undertime Hours)
 *                           − Cash Advance installment − Device − Other deductions
 *
 * Each client's own Gross Pay is its row; on the payslip the highest-paying client is the Gross Pay
 * line and every other client is Additional Pay. Hours are counted in exact minutes, so nothing is
 * lost to rounding. Absences are the scheduled working days not worked and lates come from the locked
 * attendance; the Super Admin may correct half days and additional hours during review, and those are
 * kept on recalculation. No SSS, PhilHealth, Pag-IBIG or withholding tax.
 *
 * Overtime, rewards, cash advance repayments and device deductions are not stored on the payroll
 * row: each is linked to it (payroll_overtime, payroll_rewards, cash_advance_repayments.payroll_id,
 * device_deductions.payroll_id) and summed from there, so net pay is never stored either.
 */
class PayrollCalculator
{
    /**
     * Attendance that counts as a paid working day.
     */
    private const WORKED = [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Undertime, AttendanceStatus::PaidLeave];

    public function __construct(private readonly SystemRules $rules) {}

    /**
     * Rates that pay out in this period: same pay frequency, in effect for part of the period,
     * belonging to an active Contractor or Admin. A deactivated contractor (e.g. resigned
     * mid-period) is still paid for the days they worked in the period.
     *
     * @return Builder<Rate>
     */
    public static function ratesFor(PayrollPeriod $period): Builder
    {
        $paid = User::query()->select('id')->workforce()->where(fn (Builder $query) => $query
            ->active()
            ->orWhereIn('id', ClientAssignment::query()->select('contractor_id')->whereIn('id', Attendance::query()
                ->select('assignment_id')
                ->whereDate('date', '>=', $period->start_date)
                ->whereDate('date', '<=', $period->end_date))));

        return Rate::query()
            ->with('assignment')
            ->where('pay_frequency', $period->pay_frequency)
            ->whereDate('effective_date', '<=', $period->end_date)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->start_date))
            ->forEmployee($paid);
    }

    /**
     * A contractor's gross pay for the period across all their clients (the latest rate per client).
     */
    public static function grossFor(PayrollPeriod $period, User $employee): float
    {
        return round((float) self::ratesFor($period)
            ->forEmployee($employee->id)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get()
            ->groupBy('client_id')
            ->sum(fn ($rates) => (float) $rates->last()->gross_pay), 2);
    }

    /**
     * Re-applies device, reward and cash advance amounts to an already calculated payroll, e.g. after
     * a cash advance is approved while attendance is locked.
     */
    public function refreshEmployeeDeductions(PayrollPeriod $period): void
    {
        DB::transaction(fn () => $this->applyEmployeeDeductions($period));
    }

    /**
     * Everyone who is paid in this period (and therefore notified about it).
     *
     * @return Collection<int, User>
     */
    public static function employeesFor(PayrollPeriod $period): Collection
    {
        return User::query()->whereIn('id', ClientAssignment::query()->select('contractor_id')->whereIn('id', self::ratesFor($period)->select('assignment_id')))->get();
    }

    public function calculate(PayrollPeriod $period): int
    {
        // One row per contractor and client (one assignment). If the rate changed during the period, the latest
        // rate is paid, and attendance is counted over the whole time any of that assignment's rates applied.
        $groups = self::ratesFor($period)->orderBy('effective_date')->orderBy('id')->get()->groupBy('assignment_id');
        $rates = $groups->map(fn ($group) => $group->last())->values();

        DB::transaction(function () use ($period, $rates, $groups) {
            // Rates that no longer apply drop out of the draft.
            $this->clear($period, $period->payrolls()->whereNotIn('rate_id', $rates->pluck('id'))->pluck('id')->all());

            foreach ($rates as $rate) {
                $row = Payroll::query()->firstOrNew(['period_id' => $period->id, 'assignment_id' => $rate->assignment_id]);
                $row->rate_id = $rate->id;
                $row->setRelation('rate', $rate);

                $group = $groups->get($rate->assignment_id);
                $this->applyAttendance(
                    $row,
                    $rate,
                    $period,
                    $group->min('effective_date')->max($period->start_date),
                    $group->contains(fn (Rate $each) => $each->end_date === null) ? $period->end_date : $group->max('end_date')->min($period->end_date),
                );
                if ($row->status !== PayrollStatus::Reviewed) {
                    $row->status = PayrollStatus::Draft;
                }
                $this->total($row, $rate)->save();
                $this->applyOvertime($row, $period);
            }

            $this->applyEmployeeDeductions($period);
        });

        return $rates->count();
    }

    /**
     * Removes draft rows (all of them, or the given ids), releasing anything linked to them
     * (device deductions, rewards, overtime, cash advance installments) so it is picked up again.
     *
     * @param  list<int>|null  $ids
     */
    public function clear(PayrollPeriod $period, ?array $ids = null): void
    {
        $ids ??= $period->payrolls()->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        DeviceDeduction::query()->whereIn('payroll_id', $ids)->update(['payroll_id' => null]);
        PayrollReward::query()->whereIn('payroll_id', $ids)->delete();
        PayrollOvertime::query()->whereIn('payroll_id', $ids)->delete();
        CashAdvanceRepayment::query()->whereIn('payroll_id', $ids)->delete();
        Payroll::query()->whereIn('id', $ids)->delete();
    }

    /**
     * Recomputes the stored amounts after the Super Admin adjusts a row during review. Net pay is
     * not stored; it is read from the row and what is linked to it.
     */
    public function total(Payroll $row, Rate $rate): Payroll
    {
        $hourly = $rate->hourlyRate();

        // Exact minutes, shown as hours to two decimals.
        $row->additional_minutes = (int) ($row->additional_minutes ?? 0);
        $row->late_minutes = (int) ($row->late_minutes ?? 0);
        $row->additional_pay = round($hourly * $row->additional_minutes / 60, 2);
        // Administrative deduction rules (System & Rules) can switch these off.
        $row->absence_deduction = $this->rules->enabled('absence_deductions_enabled') ? round($rate->dailyRate() * (float) $row->days_absent, 2) : 0;
        $row->late_deduction = $this->rules->enabled('late_deductions_enabled') ? round($hourly * $row->late_minutes / 60, 2) : 0;

        return $row;
    }

    /**
     * How much the deductions exceed what the row earns, if they do. Net pay stops at zero, so this
     * is shown on the row for the Super Admin to review.
     */
    public static function shortfall(Payroll $row): float
    {
        return round(max(0, -$row->unclampedNet()), 2);
    }

    /**
     * Approved overtime tickets for this contractor and client, up to the end of the period, that
     * no payroll has paid yet, are paid on this row as entered (Payroll Formula Reference).
     */
    public function applyOvertime(Payroll $row, PayrollPeriod $period): void
    {
        OvertimeRequest::query()
            ->approved()
            ->where('assignment_id', $row->assignment_id)
            ->whereDate('date', '<=', $period->end_date)
            ->notInPayroll()
            ->pluck('id')
            ->each(fn (int $id) => PayrollOvertime::query()->create(['overtime_request_id' => $id, 'payroll_id' => $row->id]));
    }

    private function applyAttendance(Payroll $row, Rate $rate, PayrollPeriod $period, CarbonInterface $from, CarbonInterface $to): void
    {
        $records = Attendance::query()
            ->with('assignmentTimeLogs')
            ->where('assignment_id', $rate->assignment_id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        $row->gross_pay = $rate->gross_pay;
        $row->late_minutes = (int) $records->sum(fn (Attendance $record) => $record->late_minutes + $record->undertime_minutes);

        // A reviewed row keeps what the Super Admin set (e.g. half days, adjusted extra hours).
        if ($row->status !== PayrollStatus::Reviewed) {
            $row->days_absent = $this->daysAbsent($rate, $records, $from, $to);
            $row->additional_minutes = $this->extraMinutes($rate, $records, $from, $to);
        }
    }

    /**
     * Additional Hours Pay basis: hours worked for this client beyond its expected hours
     * (Days per Period × Hours per Day, stored on the rate). E.g. a Part-Time client
     * expecting 40 hours that was worked 48 hours (covering a shift) has 8 extra hours. The rate itself
     * never changes because more hours were worked. Time already paid as approved overtime for this
     * client is left out, so the same hours are never paid twice.
     *
     * @param  Collection<int, Attendance>  $records
     */
    private function extraMinutes(Rate $rate, Collection $records, CarbonInterface $from, CarbonInterface $to): int
    {
        // Only a Part-Time client pays for extra hours (e.g. 48 worked against 40). Full-Time extra time is paid only as approved overtime.
        if ($rate->employment_type !== EmploymentType::PartTime) {
            return 0;
        }

        $worked = (int) round((float) $records->sum('actual_hours') * 60);
        $expected = (int) $rate->days_per_period * (int) $rate->hours_per_day * 60;
        $overtime = (int) OvertimeRequest::query()
            ->approved()
            ->where('assignment_id', $rate->assignment_id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->sum('minutes');

        return max(0, $worked - $expected - $overtime);
    }

    /**
     * Every scheduled working day that was not worked is an absence, whether it was marked absent,
     * taken as unpaid leave, or has no attendance at all (e.g. the contractor left mid-period).
     * So a fixed salary only pays for the days actually worked: Gross − Daily Rate × missing days.
     * Without a schedule for the client, the rate's days per period are the days expected.
     *
     * @param  Collection<int, Attendance>  $records
     */
    private function daysAbsent(Rate $rate, Collection $records, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());

        $worked = $records
            ->filter(fn (Attendance $record) => in_array($record->status, self::WORKED, true))
            ->map(fn (Attendance $record) => $record->date->toDateString())
            ->unique();

        $schedules = Schedule::query()
            ->with('days')
            ->where('assignment_id', $rate->assignment_id)
            ->active()
            ->whereDate('start_date', '<=', $to)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->get();

        if ($schedules->isEmpty()) {
            return max(0, (int) $rate->days_per_period - $worked->count());
        }

        $missed = 0;

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $scheduled = $schedules->contains(fn (Schedule $schedule) => $schedule->start_date->lessThanOrEqualTo($date)
                && ($schedule->end_date === null || $schedule->end_date->greaterThanOrEqualTo($date))
                && $schedule->worksOn($date));

            if ($scheduled && ! $worked->contains($date->toDateString())) {
                $missed++;
            }
        }

        // Never more absences than the days the rate pays for (e.g. a schedule with more days than the rate).
        return min($missed, (int) $rate->days_per_period);
    }

    /**
     * Per-employee amounts go on the contractor's main row (highest gross pay): approved device
     * loss/damage deductions not yet charged (each at the lost device's value), cash rewards not
     * yet paid, and this payday's cash advance installments. Always automatic.
     */
    private function applyEmployeeDeductions(PayrollPeriod $period): void
    {
        $rows = $period->payrolls()->with(['rate', 'assignment'])->orderBy('id')->get()->groupBy(fn (Payroll $row) => $row->assignment->contractor_id);

        foreach ($rows as $employeeId => $employeeRows) {
            $first = $employeeRows->sortByDesc(fn (Payroll $row) => (float) $row->gross_pay)->first();
            $rowIds = $employeeRows->pluck('id');

            DeviceDeduction::query()
                ->whereNotNull('approved_at')
                ->where(fn (Builder $query) => $query->whereNull('payroll_id')->orWhereIn('payroll_id', $rowIds))
                ->whereHas('assignment', fn (Builder $query) => $query->where('contractor_id', $employeeId))
                ->update(['payroll_id' => $first->id]);

            // Cash rewards awarded up to the end of the period and not paid yet are Additional Pay.
            PayrollReward::query()->whereIn('payroll_id', $rowIds)->delete();
            Reward::query()
                ->forEmployee($employeeId)
                ->where('reward_type', Reward::CASH)
                ->where('amount', '>', 0)
                ->whereDate('awarded_at', '<=', $period->end_date)
                ->notInPayroll()
                ->pluck('id')
                ->each(fn (int $id) => PayrollReward::query()->create(['reward_id' => $id, 'payroll_id' => $first->id]));

            // This payday's installments are planned again from scratch.
            CashAdvanceRepayment::query()->whereIn('payroll_id', $rowIds)->delete();
            $this->applyCashAdvances($period, (int) $employeeId, $first->fresh());
        }
    }

    /**
     * Each approved advance with a balance is repaid in installments over its repayment months:
     * amount ÷ (repayment months × paydays in a month), never more than what is still owed, and
     * never more than the row earns (net pay does not go below zero). An advance starts on the
     * payday it was asked for.
     */
    private function applyCashAdvances(PayrollPeriod $period, int $employeeId, Payroll $row): void
    {
        $room = max(0, $row->unclampedNet());
        $paydaysPerMonth = match ($period->pay_frequency) {
            PayFrequency::Weekly => 4,
            PayFrequency::SemiMonthly => 2,
            PayFrequency::Monthly => 1,
        };

        $advances = CashAdvance::query()
            ->outstanding()
            ->withBalance()
            ->where('contractor_id', $employeeId)
            ->where(fn (Builder $query) => $query->whereNull('payday')->orWhereDate('payday', '<=', $period->release_date))
            ->orderBy('released_date')
            ->orderBy('id')
            ->get();

        foreach ($advances as $advance) {
            $installment = round((float) $advance->amount / (max(1, (int) $advance->repayment_months) * $paydaysPerMonth), 2);
            $amount = round(min($installment, (float) $advance->remaining_balance, $room), 2);

            if ($amount <= 0) {
                continue;
            }

            CashAdvanceRepayment::query()->create([
                'advance_id' => $advance->id,
                'payroll_id' => $row->id,
                'amount' => $amount,
                'repayment_date' => $period->release_date,
                'notes' => "Payroll · {$period->period_name}",
            ]);

            $room = round($room - $amount, 2);
        }
    }
}
