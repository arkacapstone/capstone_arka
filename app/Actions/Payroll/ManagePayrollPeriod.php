<?php

namespace App\Actions\Payroll;

use App\Actions\CashAdvances\ManageCashAdvance;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Notifications\AttendanceLockedForPayroll;
use App\Notifications\PayrollVerificationOpened;
use App\Notifications\PayslipReleased;
use App\Services\ActivityLogger;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Moves a payroll period through its lifecycle (Blueprint §14):
 * Period open → Verification → Attendance lock → Review & approve → Released.
 *
 * Every step that affects contractors notifies them automatically. Verification can be opened
 * any time during the period, but only once; Locked can be toggled back until the payroll is approved.
 */
class ManagePayrollPeriod
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly ActivityLogger $activity,
        private readonly ManageCashAdvance $cashAdvances,
    ) {}

    /**
     * @param  array{period_name?: ?string, start_date: string, end_date: string, cutoff_date: string, release_date: string, pay_frequency: string}  $attributes
     */
    public function create(array $attributes): PayrollPeriod
    {
        $period = new PayrollPeriod([...$attributes, 'status' => PayrollPeriodStatus::Open]);
        $period->period_name = filled($attributes['period_name'] ?? null)
            ? $attributes['period_name']
            : $period->start_date->format('M j').' – '.$period->end_date->format('M j, Y');

        $overlaps = PayrollPeriod::query()
            ->where('pay_frequency', $period->pay_frequency)
            ->whereDate('start_date', '<=', $period->end_date)
            ->whereDate('end_date', '>=', $period->start_date)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'Another '.$period->pay_frequency->label().' period already covers part of these dates.']);
        }

        $period->save();
        $this->activity->log('payroll', 'Created payroll period', $period, $period->period_name);

        return $period;
    }

    public function advance(User $superAdmin, PayrollPeriod $period): PayrollPeriod
    {
        $next = $period->status->next()
            ?? throw ValidationException::withMessages(['status' => 'This payroll period is already released.']);

        DB::transaction(function () use ($superAdmin, $period, $next) {
            match ($next) {
                PayrollPeriodStatus::Locked => $this->calculator->calculate($period),
                PayrollPeriodStatus::Processed => $this->approveRows($superAdmin, $period),
                PayrollPeriodStatus::Released => $this->release($period),
                default => null,
            };

            $period->update([
                'status' => $next,
                // Contractors may fix their attendance until midnight of the day verification opens.
                ...($next === PayrollPeriodStatus::Verification ? ['verification_opened_at' => now()] : []),
            ]);
        });

        $this->activity->log('payroll', "Payroll period moved to {$next->label()}", $period, $period->period_name);
        $this->notifyEmployees($period, $next);

        return $period;
    }

    public function revert(PayrollPeriod $period): PayrollPeriod
    {
        $previous = $period->status->previous()
            ?? throw ValidationException::withMessages(['status' => 'This stage can no longer be undone.']);

        DB::transaction(function () use ($period, $previous) {
            if ($period->status === PayrollPeriodStatus::Locked) {
                // Unlocking reopens attendance, so the draft built from it no longer holds.
                $this->calculator->clear($period);
            }

            $period->update([
                'status' => $previous,
                // Unlocking reopens verification, with a new day to fix attendance.
                ...($previous === PayrollPeriodStatus::Verification ? ['verification_opened_at' => now()] : []),
            ]);
        });

        $this->activity->log('payroll', "Payroll period moved back to {$previous->label()}", $period, $period->period_name);

        if ($previous === PayrollPeriodStatus::Verification) {
            Notification::send(PayrollCalculator::employeesFor($period), new PayrollVerificationOpened($period, reopened: true));
        }

        return $period;
    }

    /**
     * Review adjustments while attendance is locked: additional hours, days absent (e.g. half days),
     * cash advance repayment and other approved deductions.
     *
     * @param  array{additional_minutes: int, days_absent: numeric, cash_advance_deduction: numeric, other_deductions: numeric}  $adjustments
     */
    public function adjust(Payroll $row, array $adjustments): Payroll
    {
        $row->loadMissing('period', 'rate', 'employee');

        if (! $row->period->status->allowsAdjustments()) {
            throw ValidationException::withMessages(['status' => 'Payroll rows can only be adjusted while attendance is locked and before approval.']);
        }

        $row->fill($adjustments);
        $this->calculator->total($row, $row->rate);
        $row->status = PayrollStatus::Reviewed;
        $row->save();

        $this->activity->log('payroll', 'Adjusted payroll row', $row, "{$row->employee->name} · {$row->period->period_name}");

        return $row;
    }

    public function delete(PayrollPeriod $period): void
    {
        if ($period->status !== PayrollPeriodStatus::Open) {
            throw ValidationException::withMessages(['status' => 'Only an open period with no payroll can be deleted.']);
        }

        $this->calculator->clear($period);
        $period->delete();
        $this->activity->log('payroll', 'Deleted payroll period', null, $period->period_name);
    }

    /**
     * Payslips become available, and cash advance deductions become recorded repayments.
     */
    private function release(PayrollPeriod $period): void
    {
        $period->payrolls()->update(['status' => PayrollStatus::Released]);
        $this->cashAdvances->applyPayroll($period);
    }

    private function approveRows(User $superAdmin, PayrollPeriod $period): void
    {
        if (! $period->payrolls()->exists()) {
            throw ValidationException::withMessages(['status' => 'There is no payroll to approve. Check that contractors have rates for this pay frequency.']);
        }

        $period->payrolls()->update([
            'status' => PayrollStatus::Approved,
            'approved_by' => $superAdmin->id,
            'approved_at' => now(),
        ]);
    }

    private function notifyEmployees(PayrollPeriod $period, PayrollPeriodStatus $stage): void
    {
        match ($stage) {
            PayrollPeriodStatus::Verification => Notification::send(PayrollCalculator::employeesFor($period), new PayrollVerificationOpened($period)),
            PayrollPeriodStatus::Locked => Notification::send(PayrollCalculator::employeesFor($period), new AttendanceLockedForPayroll($period)),
            PayrollPeriodStatus::Released => Notification::send(
                User::query()->whereIn('id', $period->payrolls()->select('employee_id'))->get(),
                new PayslipReleased($period),
            ),
            default => null,
        };
    }
}
