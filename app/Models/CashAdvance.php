<?php

namespace App\Models;

use App\Enums\CashAdvanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'amount', 'requested_amount', 'gross_pay', 'payday', 'remaining_balance', 'reason', 'status', 'approved_by', 'approved_at', 'released_date'])]
class CashAdvance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            // The contractor's gross pay for the pay period it was requested in (the limit), and that payday.
            'gross_pay' => 'decimal:2',
            'payday' => 'immutable_date',
            'remaining_balance' => 'decimal:2',
            'status' => CashAdvanceStatus::class,
            'approved_at' => 'datetime',
            'released_date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
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
     * Released advances that still have a balance to repay.
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->where('status', CashAdvanceStatus::Approved)->where('remaining_balance', '>', 0);
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', CashAdvanceStatus::Pending);
    }
}
