<?php

namespace App\Actions\CashAdvances;

use App\Enums\CashAdvanceStatus;
use App\Enums\PayrollPeriodStatus;
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
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollPeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cash advances (Blueprint §13): requests, approval, the released amount, repayment tracking
 * and the remaining balance. Only the Super Admin decides. Repayments are deducted through
 * payroll (a separate payslip line).
 */
class ManageCashAdvance
{
    /**
     * Payroll stages in which a new cash advance can still be added to the period's payroll.
     */
    private const REPAYABLE = [PayrollPeriodStatus::Open, PayrollPeriodStatus::Verification, PayrollPeriodStatus::Locked];

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
        private readonly PayrollCalculator $calculator,
        private readonly PayrollPeriodResolver $periods,
    ) {}

    /**
     * Whether the contractor can request a cash advance today, and for how much: any day of the pay
     * period they are in, once a month, up to their gross pay for that period. It is repaid in full on
     * that period's payday. The period need not be created yet; it is projected from System & Rules.
     *
     * @return array{open: bool, reason: ?string, period: ?PayrollPeriod, limit: float}
     */
    public function window(User $employee, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        // A created period covering today that pays this contractor, otherwise the projected one.
        $period = PayrollPeriod::query()
            ->covering($today)
            ->orderByDesc('start_date')
            ->get()
            ->push($this->periods->projectSemiMonthly($today))
            ->first(fn (PayrollPeriod $period) => PayrollCalculator::ratesFor($period)->where('employee_id', $employee->id)->exists());

        $limit = $period ? PayrollCalculator::grossFor($period, $employee) : 0.0;

        $reason = match (true) {
            $employee->cashAdvances()->pending()->exists() => 'You already have a request waiting for a decision.',
            $employee->cashAdvances()
                ->whereNotIn('status', [CashAdvanceStatus::Rejected, CashAdvanceStatus::Cancelled])
                ->whereBetween('created_at', [$today->startOfMonth(), $today->endOfMonth()])
                ->exists() => 'You can get one cash advance a month, and you already have one for '.$today->format('F').'.',
            $period === null || $limit <= 0 => 'You have no pay in this pay period to repay a cash advance from.',
            ! in_array($period->status, self::REPAYABLE, true) => "Payroll for {$period->period_name} is already approved. You can ask again in the next pay period.",
            default => null,
        };

        return ['open' => $reason === null, 'reason' => $reason, 'period' => $period, 'limit' => round($limit, 2)];
    }

    public function request(User $employee, float $amount, string $reason): CashAdvance
    {
        $window = $this->window($employee);

        if (! $window['open']) {
            throw ValidationException::withMessages(['amount' => $window['reason']]);
        }

        if ($amount > $window['limit']) {
            throw ValidationException::withMessages(['amount' => 'The most you can request for this payday is ₱'.number_format($window['limit'], 2).'.']);
        }

        $advance = $employee->cashAdvances()->create([
            'amount' => $amount,
            'requested_amount' => $amount,
            'gross_pay' => $window['limit'],
            'payday' => $window['period']->release_date,
            'remaining_balance' => $amount,
            'reason' => $reason,
            'status' => CashAdvanceStatus::Pending,
        ]);

        $this->activity->log('cash-advances', 'Requested cash advance', $advance, "{$employee->name} · ₱".number_format($amount, 2));

        $this->notifier->superAdmins(new CashAdvanceRequested($advance));

        return $advance;
    }

    /**
     * The Super Admin releases the advance, for the requested amount or less. It is added to that
     * period's payroll, so it can no longer be approved once that payroll has been approved.
     */
    public function approve(User $superAdmin, CashAdvance $advance, CarbonImmutable $releasedOn, ?float $amount = null): CashAdvance
    {
        $this->ensurePending($advance);

        $requested = (float) ($advance->requested_amount ?? $advance->amount);
        $amount ??= $requested;

        if ($amount < 1 || $amount > $requested) {
            throw ValidationException::withMessages(['amount' => 'Approve between ₱1.00 and the requested ₱'.number_format($requested, 2).'.']);
        }

        // The payroll for that payday, if it has been created.
        $periods = $advance->payday ? PayrollPeriod::query()->whereDate('release_date', $advance->payday)->get() : collect();
        $closed = $periods->first(fn (PayrollPeriod $period) => ! in_array($period->status, self::REPAYABLE, true));

        if ($closed) {
            throw ValidationException::withMessages(['status' => "Payroll for {$closed->period_name} is already approved, so this advance can't be repaid on that payday. Reject it; the contractor can ask again in the next pay period."]);
        }

        $advance->update([
            'status' => CashAdvanceStatus::Approved,
            'approved_by' => $superAdmin->id,
            'approved_at' => now(),
            'released_date' => $releasedOn,
            'amount' => $amount,
            'remaining_balance' => $amount,
        ]);

        // Payroll already calculated: put the repayment on it now.
        $periods->where('status', PayrollPeriodStatus::Locked)->each(fn (PayrollPeriod $period) => $this->calculator->refreshEmployeeDeductions($period));

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
                // Only what the pay actually covered is repaid; the rest stays owed for the next payday.
                $left = max(0, round((float) $row->cash_advance_deduction - PayrollCalculator::shortfall($row), 2));

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
