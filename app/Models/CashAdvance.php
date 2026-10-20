<?php

namespace App\Models;

use App\Enums\CashAdvanceStatus;
use App\Models\Concerns\BelongsToContractor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cash advance. The contractor repays it over repayment_months (2 or 3) months.
 *
 * The remaining balance and "repaid" are not stored: remaining = amount − the repayments
 * (the cash_advance_balances view does the same in SQL). status only holds the decision.
 */
#[Fillable(['contractor_id', 'employee_id', 'amount', 'requested_amount', 'repayment_months', 'payday', 'reason', 'status', 'approved_by', 'approved_at', 'released_date'])]
class CashAdvance extends Model
{
    use BelongsToContractor, HasFactory;

    /**
     * Repayment terms a contractor can pick, in months.
     */
    public const REPAYMENT_MONTHS = [2, 3];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'repayment_months' => 'integer',
            'payday' => 'immutable_date',
            'status' => CashAdvanceStatus::class,
            'approved_at' => 'datetime',
            'released_date' => 'immutable_date',
        ];
    }

    /**
     * Amount less what has been repaid. Uses the withBalance() sum or loaded repayments when
     * present, so lists don't query once per advance.
     *
     * @return Attribute<float, never>
     */
    protected function remainingBalance(): Attribute
    {
        return Attribute::make(set: fn () => [], get: function () {
            $repaid = match (true) {
                array_key_exists('repayments_sum_amount', $this->attributes) => (float) $this->attributes['repayments_sum_amount'],
                $this->relationLoaded('repayments') => (float) $this->repayments->sum('amount'),
                default => (float) $this->repayments()->sum('amount'),
            };

            return number_format(max(0, round((float) $this->amount - $repaid, 2)), 2, '.', '');
        });
    }

    /**
     * Amount ÷ repayment months: what is deducted each month.
     *
     * @return Attribute<float, never>
     */
    protected function monthlyInstallment(): Attribute
    {
        return Attribute::get(fn () => number_format((float) $this->amount / max(1, (int) $this->repayment_months), 2, '.', ''));
    }

    /**
     * Approved and fully paid back.
     */
    public function isRepaid(): bool
    {
        return $this->status === CashAdvanceStatus::Approved && $this->remaining_balance <= 0;
    }

    /**
     * The status to show: "Fully repaid" is not stored, it is an approved advance with no balance left.
     */
    public function displayStatus(): CashAdvanceStatus
    {
        return $this->isRepaid() ? CashAdvanceStatus::Repaid : $this->status;
    }

    /**
     * Approved and paid back, or rejected, or cancelled: nothing left to act on.
     */
    #[Scope]
    protected function closed(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [CashAdvanceStatus::Rejected, CashAdvanceStatus::Cancelled])
            ->orWhere(fn (Builder $query) => $query->where('status', CashAdvanceStatus::Approved)->where('amount', '<=', CashAdvanceRepayment::query()
                ->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('advance_id', 'cash_advances.id'))));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CashAdvanceRepayment, $this>
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(CashAdvanceRepayment::class, 'advance_id');
    }

    /**
     * Approved advances that still have a balance to repay.
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->where('status', CashAdvanceStatus::Approved)->where('amount', '>', CashAdvanceRepayment::query()
            ->selectRaw('coalesce(sum(amount), 0)')
            ->whereColumn('advance_id', 'cash_advances.id'));
    }

    /**
     * Loads the repaid total with each advance, for remaining_balance.
     */
    #[Scope]
    protected function withBalance(Builder $query): void
    {
        $query->withSum('repayments', 'amount');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', CashAdvanceStatus::Pending);
    }
}
