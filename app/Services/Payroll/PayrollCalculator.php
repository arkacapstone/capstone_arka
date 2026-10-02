<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\DeviceDeduction;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
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
 * Draft payroll for a period, one row per client rate (Payroll Formula & Scenario Reference):
 *
 *   Hourly Rate           = Gross Pay ÷ (Expected Working Days × Expected Hours per Day)
 *   Daily Rate            = Gross Pay ÷ Expected Working Days
 *   Additional Hours Pay  = Hourly Rate × hours worked beyond that Part-Time client's expected hours
 *   Overtime Pay          = Hourly Rate × (approved overtime minutes ÷ 60), no premium
 *   Net Pay               = Gross Pay + Additional Hours Pay + Overtime Pay + Rewards
 *                           − (Daily Rate × Days Absent) − (Hourly Rate × Late/Undertime Hours)
 *                           − Cash Advance (in full) − Device − Other deductions
 *
 * Each client's own Gross Pay is its row; on the payslip the highest-paying client is the Gross Pay
 * line and every other client is Additional Pay. Hours are counted in exact minutes, so nothing is
 * lost to rounding. Absences are the scheduled working days not worked and lates come from the locked
 * attendance; the Super Admin may correct half days and additional hours during review, and those are
 * kept on recalculation. No SSS, PhilHealth, Pag-IBIG or withholding tax.
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
        return Rate::query()
            ->where('pay_frequency', $period->pay_frequency)
            ->whereDate('effective_date', '<=', $period->end_date)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->start_date))
            ->whereHas('employee', fn (Builder $query) => $query->workforce()->where(fn (Builder $query) => $query
                ->active()
                ->orWhereHas('attendances', fn (Builder $query) => $query->whereDate('date', '>=', $period->start_date)->whereDate('date', '<=', $period->end_date))));
    }

    /**
     * Everyone who is paid in this period (and therefore notified about it).
     *
     * @return Collection<int, User>
     */
    public static function employeesFor(PayrollPeriod $period): Collection
    {
        return User::query()->whereIn('id', self::ratesFor($period)->select('employee_id'))->get();
    }

    public function calculate(PayrollPeriod $period): int
    {
        $rates = self::ratesFor($period)->with('employee')->get();

        DB::transaction(function () use ($period, $rates) {
            // Rates that no longer apply drop out of the draft.
            $this->clear($period, $period->payrolls()->whereNotIn('rate_id', $rates->pluck('id'))->pluck('id')->all());

            foreach ($rates as $rate) {
                $row = Payroll::query()->firstOrNew(['period_id' => $period->id, 'rate_id' => $rate->id]);
                $row->fill(['employee_id' => $rate->employee_id, 'client_id' => $rate->client_id]);

                $this->applyAttendance($row, $rate, $period);
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
     * Removes draft rows (all of them, or the given ids), releasing any device deductions
     * charged to them so they are picked up again next time.
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
        Reward::query()->whereIn('payroll_id', $ids)->update(['payroll_id' => null]);
        OvertimeRequest::query()->whereIn('payroll_id', $ids)->update(['payroll_id' => null]);
        Payroll::query()->whereIn('id', $ids)->delete();
    }

    /**
     * Recomputes net pay after the Super Admin adjusts a row during review.
     */
    public function total(Payroll $row, Rate $rate): Payroll
    {
        $hourly = $rate->hourlyRate();

        $row->hourly_rate = round($hourly, 4);
        $row->daily_rate = round($rate->dailyRate(), 2);
        // Exact minutes, shown as hours to two decimals.
        $row->additional_minutes = (int) ($row->additional_minutes ?? 0);
        $row->late_minutes = (int) ($row->late_minutes ?? 0);
        $row->additional_hours = round($row->additional_minutes / 60, 2);
        $row->late_hours = round($row->late_minutes / 60, 2);
        $row->additional_pay = round($hourly * $row->additional_minutes / 60, 2);
        // Administrative deduction rules (System & Rules) can switch these off.
        $row->absence_deduction = $this->rules->enabled('absence_deductions_enabled') ? round($rate->dailyRate() * (float) $row->days_absent, 2) : 0;
        $row->late_deduction = $this->rules->enabled('late_deductions_enabled') ? round($hourly * $row->late_minutes / 60, 2) : 0;

        $row->net_pay = round(
            (float) $row->gross_pay
            + (float) $row->additional_pay
            + (float) ($row->overtime_amount ?? 0)
            + (float) ($row->reward_amount ?? 0)
            - (float) $row->absence_deduction
            - (float) $row->late_deduction
            - (float) ($row->cash_advance_deduction ?? 0)
            - (float) ($row->device_deduction ?? 0)
            - (float) ($row->other_deductions ?? 0),
            2,
        );

        return $row;
    }

    /**
     * Approved overtime tickets for this contractor and client, up to the end of the period, that
     * no payroll has paid yet, are paid on this row as entered (Payroll Formula Reference).
     */
    public function applyOvertime(Payroll $row, PayrollPeriod $period): void
    {
        OvertimeRequest::query()
            ->approved()
            ->where('employee_id', $row->employee_id)
            ->where('client_id', $row->client_id)
            ->whereDate('date', '<=', $period->end_date)
            ->whereNull('payroll_id')
            ->update(['payroll_id' => $row->id]);

        $overtime = round((float) OvertimeRequest::query()->where('payroll_id', $row->id)->sum('amount'), 2);

        if ($overtime !== round((float) $row->overtime_amount, 2)) {
            $row->overtime_amount = $overtime;
            $this->total($row, $row->rate ?? Rate::find($row->rate_id))->save();
        }
    }

    private function applyAttendance(Payroll $row, Rate $rate, PayrollPeriod $period): void
    {
        $from = $rate->effective_date->max($period->start_date);
        $to = $rate->end_date ? $rate->end_date->min($period->end_date) : $period->end_date;

        $records = Attendance::query()
            ->where('employee_id', $rate->employee_id)
            ->where('client_id', $rate->client_id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        if ($rate->pay_frequency === PayFrequency::Hourly) {
            // Hourly arrangements are paid for the hours actually worked.
            $row->gross_pay = round((float) $rate->gross_pay * (float) $records->sum('actual_hours'), 2);
            $row->days_absent = 0;
            $row->late_minutes = 0;

            return;
        }

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
     * (Expected Working Days × Expected Hours per Day, stored on the rate). E.g. a Part-Time client
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
        $expected = (int) $rate->working_days * (int) $rate->hours_per_day * 60;
        $overtime = (int) OvertimeRequest::query()
            ->approved()
            ->where('employee_id', $rate->employee_id)
            ->where('client_id', $rate->client_id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->sum('minutes');

        return max(0, $worked - $expected - $overtime);
    }

    /**
     * Every scheduled working day that was not worked is an absence, whether it was marked absent,
     * taken as unpaid leave, or has no attendance at all (e.g. the contractor left mid-period).
     * So a fixed salary only pays for the days actually worked: Gross − Daily Rate × missing days.
     * Without a schedule for the client, the rate's working days are the days expected.
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
            ->where('employee_id', $rate->employee_id)
            ->where('client_id', $rate->client_id)
            ->active()
            ->whereDate('start_date', '<=', $to)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->get();

        if ($schedules->isEmpty()) {
            return max(0, (int) $rate->working_days - $worked->count());
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

        return $missed;
    }

    /**
     * Per-employee deductions go on the contractor's first row: approved device loss/damage
     * deductions not yet charged (each at the lost device's value), and every outstanding cash advance
     * in full (given before payday, deducted all at once). A reviewed row keeps its manual amount.
     */
    private function applyEmployeeDeductions(PayrollPeriod $period): void
    {
        $rows = $period->payrolls()->with('rate')->orderBy('id')->get()->groupBy('employee_id');

        foreach ($rows as $employeeId => $employeeRows) {
            $first = $employeeRows->first();

            DeviceDeduction::query()
                ->whereNotNull('approved_at')
                ->where(fn (Builder $query) => $query->whereNull('payroll_id')->orWhereIn('payroll_id', $employeeRows->pluck('id')))
                ->whereHas('assignment', fn (Builder $query) => $query->where('employee_id', $employeeId))
                ->update(['payroll_id' => $first->id]);

            // Rewards with an amount, awarded up to the end of the period and not paid yet, are Additional Pay.
            Reward::query()
                ->where('employee_id', $employeeId)
                ->where('amount', '>', 0)
                ->whereDate('awarded_at', '<=', $period->end_date)
                ->where(fn (Builder $query) => $query->whereNull('payroll_id')->orWhereIn('payroll_id', $employeeRows->pluck('id')))
                ->update(['payroll_id' => $first->id]);

            $outstanding = (float) CashAdvance::query()->outstanding()->where('employee_id', $employeeId)->sum('remaining_balance');

            foreach ($employeeRows as $row) {
                // Each lost device is deducted at its own value, never capped to a fixed amount.
                $row->device_deduction = round((float) DeviceDeduction::query()->where('payroll_id', $row->id)->sum('amount'), 2);
                $row->reward_amount = round((float) Reward::query()->where('payroll_id', $row->id)->sum('amount'), 2);

                if ($row->status !== PayrollStatus::Reviewed) {
                    $row->cash_advance_deduction = $row->is($first) ? round($outstanding, 2) : 0;
                }

                $this->total($row, $row->rate)->save();
            }
        }
    }
}
