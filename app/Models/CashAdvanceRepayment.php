<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One repayment against a cash advance: deducted through payroll (payroll_id set) or paid directly.
 */
#[Fillable(['advance_id', 'payroll_id', 'amount', 'repayment_date', 'notes'])]
class CashAdvanceRepayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'repayment_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CashAdvance, $this>
     */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class, 'advance_id');
    }

    /**
     * @return BelongsTo<Payroll, $this>
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
