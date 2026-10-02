<?php

namespace App\Actions\CashAdvances;

use App\Enums\CashAdvanceStatus;
use App\Enums\PayrollStatus;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRepayment;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Notifications\CashAdvanceDecided;
use App\Notifications\CashAdvanceRequested;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cash advances (Blueprint §13): requests, approval, the released amount, repayment tracking
 * and the remaining balance. Only the Super Admin decides. Repayments are deducted through
 * payroll (a separate payslip line).
 */
class ManageCashAdvance
{
    public function __construct(
        private readonly SystemRules $rules,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    public function request(User $employee, float $amount, string $reason, ?User $recordedBy = null): CashAdvance
    {
        $max = $this->rules->decimal('cash_advance_max_amount');

        if ($max > 0 && $amount > $max) {
            throw ValidationException::withMessages(['amount' => 'The maximum cash advance is ₱'.number_format($max, 2).' (System & Rules).']);
        }

        if ($employee->cashAdvances()->where('status', CashAdvanceStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['amount' => 'There is already a cash advance request waiting for a decision.']);
        }

        $advance = $employee->cashAdvances()->create([
            'amount' => $amount,
            'remaining_balance' => $amount,
            'reason' => $reason,
            'status' => CashAdvanceStatus::Pending,
        ]);

        $this->activity->log('cash-advances', 'Requested cash advance', $advance, "{$employee->name} · ₱".number_format($amount, 2));

        // A request the Super Admin records on someone's behalf needs no alert to themselves.
        if ($recordedBy === null || ! $recordedBy->isSuperAdmin()) {
            $this->notifier->superAdmins(new CashAdvanceRequested($advance));
        }

        return $advance;
    }

    public function approve(User $superAdmin, CashAdvance $advance, CarbonImmutable $releasedOn): CashAdvance
    {
        $this->ensurePending($advance);

        $advance->update([
            'status' => CashAdvanceStatus::Approved,
            'approved_by' => $superAdmin->id,
            'approved_at' => now(),
            'released_date' => $releasedOn,
            'remaining_balance' => $advance->amount,
        ]);

        return $this->decided($advance, 'Approved and released cash advance');
    }

    public function reject(User $superAdmin, CashAdvance $advance): CashAdvance
    {
        $this->ensurePending($advance);

        $advance->update(['status' => CashAdvanceStatus::Rejected, 'approved_by' => $superAdmin->id, 'approved_at' => now()]);

        return $this->decided($advance, 'Rejected cash advance');
    }

    public function cancel(CashAdvance $advance): CashAdvance
    {
        $this->ensurePending($advance);
        $advance->update(['status' => CashAdvanceStatus::Cancelled]);
        $this->activity->log('cash-advances', 'Cancelled cash advance request', $advance);

        return $advance;
    }

    /**
     * When payslips are released, each cash advance deduction on the payroll becomes a
     * repayment against the contractor's oldest outstanding advances. Held payroll is not paid
     * yet, so it is applied once its hold is lifted (for that one contractor).
     */
    public function applyPayroll(PayrollPeriod $period, ?int $employeeId = null): void
    {
        $period->payrolls()
            ->where('status', PayrollStatus::Released)
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->where('cash_advance_deduction', '>', 0)
            ->get()
            ->each(function (Payroll $row) use ($period) {
                $left = (float) $row->cash_advance_deduction;

                CashAdvance::query()
                    ->outstanding()
                    ->where('employee_id', $row->employee_id)
                    ->orderBy('released_date')
                    ->orderBy('id')
                    ->get()
                    ->each(function (CashAdvance $advance) use (&$left, $row, $period) {
                        if ($left <= 0) {
                            return false;
                        }

                        $amount = min($left, (float) $advance->remaining_balance);
                        $this->repay($advance, $amount, $period->release_date, "Payroll · {$period->period_name}", $row);
                        $left = round($left - $amount, 2);

                        return true;
                    });
            });
    }

    private function repay(CashAdvance $advance, float $amount, CarbonImmutable $date, ?string $notes, ?Payroll $payroll = null): CashAdvanceRepayment
    {
        $repayment = $advance->repayments()->create([
            'payroll_id' => $payroll?->id,
            'amount' => round($amount, 2),
            'repayment_date' => $date,
            'notes' => $notes,
        ]);

        $remaining = round((float) $advance->remaining_balance - $amount, 2);
        $advance->update([
            'remaining_balance' => max(0, $remaining),
            'status' => $remaining <= 0 ? CashAdvanceStatus::Repaid : CashAdvanceStatus::Approved,
        ]);

        return $repayment;
    }

    private function ensurePending(CashAdvance $advance): void
    {
        if ($advance->status !== CashAdvanceStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This cash advance has already been decided.']);
        }
    }

    private function decided(CashAdvance $advance, string $action): CashAdvance
    {
        $advance->loadMissing('employee');
        $this->activity->log('cash-advances', $action, $advance, "{$advance->employee->name} · ₱".number_format((float) $advance->amount, 2));
        $advance->employee->notify(new CashAdvanceDecided($advance));

        return $advance;
    }
}
