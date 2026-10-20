<?php

namespace App\Models\Concerns;

use App\Models\Payroll;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * For overtime and rewards, whose payroll line is kept in a junction table (payroll_overtime,
 * payroll_rewards) instead of a payroll_id column. payroll() reaches the line; payroll_id can
 * still be read and set, and setting it writes the junction row when the record is saved.
 *
 * The using model defines PAYROLL_JUNCTION (the junction model class) and PAYROLL_KEY (its
 * column pointing back to this record).
 */
trait PaidInPayroll
{
    private bool $payrollIdSet = false;

    private ?int $pendingPayrollId = null;

    public static function bootPaidInPayroll(): void
    {
        static::saved(function ($model) {
            if (! $model->payrollIdSet) {
                return;
            }

            $model->payrollIdSet = false;
            $junction = static::PAYROLL_JUNCTION;
            $junction::query()->where(static::PAYROLL_KEY, $model->getKey())->delete();

            if ($model->pendingPayrollId !== null) {
                $junction::query()->create([static::PAYROLL_KEY => $model->getKey(), 'payroll_id' => $model->pendingPayrollId]);
            }

            $model->unsetRelation('payroll');
        });
    }

    /**
     * @return HasOneThrough<Payroll, Model, $this>
     */
    public function payroll(): HasOneThrough
    {
        return $this->hasOneThrough(Payroll::class, static::PAYROLL_JUNCTION, static::PAYROLL_KEY, 'id', 'id', 'payroll_id');
    }

    protected function payrollId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payrollIdSet ? $this->pendingPayrollId : $this->payroll?->id,
            set: function ($value) {
                $this->payrollIdSet = true;
                $this->pendingPayrollId = $value === null ? null : (int) $value;

                return [];
            },
        );
    }

    /**
     * Not paid in any payroll yet.
     */
    #[Scope]
    protected function notInPayroll(Builder $query): void
    {
        $query->whereDoesntHave('payroll');
    }

    /**
     * Paid in one of these payroll lines (an id or a list).
     */
    #[Scope]
    protected function inPayroll(Builder $query, mixed $payrollIds): void
    {
        $junction = static::PAYROLL_JUNCTION;

        $query->whereIn($this->qualifyColumn('id'), $junction::query()->select(static::PAYROLL_KEY)->whereIn('payroll_id', is_int($payrollIds) ? [$payrollIds] : $payrollIds));
    }
}
