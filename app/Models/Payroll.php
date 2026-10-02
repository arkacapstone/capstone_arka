<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('payroll')]
#[Fillable([
    'period_id', 'employee_id', 'client_id', 'rate_id',
    'gross_pay', 'hourly_rate', 'daily_rate',
    'additional_hours', 'additional_minutes', 'additional_pay',
    'days_absent', 'absence_deduction',
    'late_hours', 'late_minutes', 'late_deduction',
    'overtime_amount', 'reward_amount', 'cash_advance_deduction', 'device_deduction', 'other_deductions',
    'net_pay', 'status', 'approved_by', 'approved_at',
])]
class Payroll extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'hourly_rate' => 'decimal:4',
            'daily_rate' => 'decimal:2',
            'additional_hours' => 'decimal:2',
            'additional_minutes' => 'integer',
            'additional_pay' => 'decimal:2',
            'days_absent' => 'decimal:1',
            'absence_deduction' => 'decimal:2',
            'late_hours' => 'decimal:2',
            'late_minutes' => 'integer',
            'late_deduction' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'reward_amount' => 'decimal:2',
            'cash_advance_deduction' => 'decimal:2',
            'device_deduction' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'status' => PayrollStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    /**
     * Rewards with an amount paid on this row as Additional Pay.
     *
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * @return BelongsTo<Rate, $this>
     */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
