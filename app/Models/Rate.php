<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contractor's pay arrangement with one client (Blueprint §5), set by the Super Admin.
 *
 * Rates are never edited once they may have been used by payroll: a change ends the
 * current rate and starts a new one, so every payroll keeps the rate that applied.
 */
#[Fillable(['employee_id', 'client_id', 'employment_type', 'gross_pay', 'pay_frequency', 'working_days', 'hours_per_day', 'effective_date', 'end_date'])]
class Rate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'gross_pay' => 'decimal:2',
            'pay_frequency' => PayFrequency::class,
            'working_days' => 'integer',
            'hours_per_day' => 'integer',
            'effective_date' => 'immutable_date',
            'end_date' => 'immutable_date',
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Assignments that have not been ended.
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull('end_date');
    }

    /**
     * Hourly Rate = Gross Pay ÷ (Working Days × Hours per Day) (Blueprint §11).
     */
    public function hourlyRate(): float
    {
        return (float) $this->gross_pay / ($this->working_days * $this->hours_per_day);
    }

    /**
     * Daily Rate = Gross Pay ÷ Working Days (Blueprint §11).
     */
    public function dailyRate(): float
    {
        return (float) $this->gross_pay / $this->working_days;
    }
}
