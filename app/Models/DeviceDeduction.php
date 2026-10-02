<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_assignment_id', 'payroll_id', 'amount', 'reason', 'approved_by', 'approved_at'])]
class DeviceDeduction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DeviceAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DeviceAssignment::class, 'device_assignment_id');
    }

    /**
     * @return BelongsTo<Payroll, $this>
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * Device loss/damage deductions still awaiting the Super Admin decision.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('approved_at');
    }
}
