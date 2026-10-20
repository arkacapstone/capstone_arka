<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Models\Concerns\BelongsToAssignment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A contractor's pay arrangement with one client (Blueprint §5), set by the Super Admin.
 * Rates are never edited once they may have been used by payroll: a change ends the
 * current rate and starts a new one, so every payroll keeps the rate that applied.
 *
 * Belongs to a client assignment. Full-Time / Part-Time is the assignment's (set here, saved
 * there). days_per_period is the number of paid days in one pay period; working_days is kept
 * as another name for it.
 */
#[Fillable(['assignment_id', 'employee_id', 'client_id', 'employment_type', 'gross_pay', 'pay_frequency', 'days_per_period', 'working_days', 'hours_per_day', 'effective_date', 'end_date'])]
class Rate extends Model
{
    use BelongsToAssignment, HasFactory;

    private bool $employmentTypeSet = false;

    private ?EmploymentType $pendingEmploymentType = null;

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'pay_frequency' => PayFrequency::class,
            'days_per_period' => 'integer',
            'hours_per_day' => 'integer',
            'effective_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        // Runs after the assignment is picked (BelongsToAssignment), so it can be updated.
        static::saving(function (Rate $rate) {
            if ($rate->employmentTypeSet && $rate->assignment !== null) {
                $rate->assignment->update(['employment_type' => $rate->pendingEmploymentType]);
                $rate->employmentTypeSet = false;
            }
        });
    }

    protected function workingDays(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->days_per_period,
            set: fn ($value) => ['days_per_period' => $value],
        );
    }

    protected function employmentType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->employmentTypeSet ? $this->pendingEmploymentType : $this->assignment?->employment_type,
            set: function ($value) {
                $this->employmentTypeSet = true;
                $this->pendingEmploymentType = $value instanceof EmploymentType ? $value : EmploymentType::tryFrom((string) $value);

                return [];
            },
        );
    }

    /**
     * Assignments that have not been ended.
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('end_date'));
    }

    /**
     * Hourly Rate = Gross Pay ÷ (Days per Period × Hours per Day) (Blueprint §11).
     */
    public function hourlyRate(): float
    {
        return (float) $this->gross_pay / ($this->days_per_period * $this->hours_per_day);
    }

    /**
     * Daily Rate = Gross Pay ÷ Days per Period (Blueprint §11).
     */
    public function dailyRate(): float
    {
        return (float) $this->gross_pay / $this->days_per_period;
    }
}
