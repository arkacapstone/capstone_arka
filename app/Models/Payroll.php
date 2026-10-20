<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\BelongsToAssignment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payslip line per client assignment per period.
 *
 * Stored (snapshots computed with rates and rules that can change): gross pay, additional
 * minutes and pay, days absent, absence deduction, late minutes and deduction, other deductions.
 *
 * Not stored, read from their own tables (the payroll_summary view does the same in SQL):
 * overtime (payroll_overtime), rewards (payroll_rewards), cash advance repayments, device
 * deductions, and net pay. Hourly and daily rate come from the rate; hours from the minutes.
 * While payroll is being calculated those values can be set on the row; they are not saved.
 */
#[Table('payroll')]
#[Fillable([
    'period_id', 'assignment_id', 'employee_id', 'client_id', 'rate_id',
    'gross_pay', 'additional_minutes', 'additional_pay',
    'days_absent', 'absence_deduction',
    'late_minutes', 'late_deduction', 'other_deductions',
    'overtime_amount', 'reward_amount', 'cash_advance_deduction', 'device_deduction', 'net_pay',
    'status', 'approved_by', 'approved_at', 'held_at', 'hold_reason',
])]
class Payroll extends Model
{
    use BelongsToAssignment, HasFactory;

    /**
     * Summed amounts and net pay set while calculating (not saved).
     *
     * @var array<string, float>
     */
    protected array $computed = [];

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'additional_minutes' => 'integer',
            'additional_pay' => 'decimal:2',
            'days_absent' => 'decimal:1',
            'absence_deduction' => 'decimal:2',
            'late_minutes' => 'integer',
            'late_deduction' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'status' => PayrollStatus::class,
            'approved_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    /**
     * Loads overtime, rewards, cash advance and device amounts with each row (one query for a list).
     */
    #[Scope]
    protected function withAmounts(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query
            ->selectSub(OvertimeRequest::query()->selectRaw('coalesce(sum(overtime_requests.amount), 0)')
                ->join('payroll_overtime', 'payroll_overtime.overtime_request_id', '=', 'overtime_requests.id')
                ->whereColumn('payroll_overtime.payroll_id', 'payroll.id'), 'overtime_amount_total')
            ->selectSub(Reward::query()->selectRaw('coalesce(sum(rewards.amount), 0)')
                ->join('payroll_rewards', 'payroll_rewards.reward_id', '=', 'rewards.id')
                ->whereColumn('payroll_rewards.payroll_id', 'payroll.id'), 'reward_amount_total')
            ->selectSub(CashAdvanceRepayment::query()->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('cash_advance_repayments.payroll_id', 'payroll.id'), 'cash_advance_deduction_total')
            ->selectSub(DeviceDeduction::query()->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('device_deductions.payroll_id', 'payroll.id'), 'device_deduction_total');
    }

    private function summed(string $field, callable $query): Attribute
    {
        return Attribute::make(
            get: function () use ($field, $query) {
                if (array_key_exists($field, $this->computed)) {
                    return self::decimal($this->computed[$field]);
                }

                if (array_key_exists("{$field}_total", $this->attributes)) {
                    return self::decimal($this->attributes["{$field}_total"]);
                }

                return self::decimal($this->exists ? $query() : 0);
            },
            set: function ($value) use ($field) {
                $this->computed[$field] = round((float) $value, 2);

                return [];
            },
        );
    }

    protected function overtimeAmount(): Attribute
    {
        return $this->summed('overtime_amount', fn () => $this->overtimeRequests()->sum('overtime_requests.amount'));
    }

    protected function rewardAmount(): Attribute
    {
        return $this->summed('reward_amount', fn () => $this->rewards()->sum('rewards.amount'));
    }

    protected function cashAdvanceDeduction(): Attribute
    {
        return $this->summed('cash_advance_deduction', fn () => $this->cashAdvanceRepayments()->sum('amount'));
    }

    protected function deviceDeduction(): Attribute
    {
        return $this->summed('device_deduction', fn () => $this->deviceDeductions()->sum('amount'));
    }

    /**
     * Gross + additional + overtime + rewards − absences − late − cash advance − device − other,
     * never below zero.
     */
    protected function netPay(): Attribute
    {
        return Attribute::make(
            get: fn () => self::decimal($this->computed['net_pay'] ?? max(0, $this->unclampedNet())),
            set: function ($value) {
                $this->computed['net_pay'] = round((float) $value, 2);

                return [];
            },
        );
    }

    public function unclampedNet(): float
    {
        return round(
            (float) $this->gross_pay
            + (float) $this->additional_pay
            + (float) $this->overtime_amount
            + (float) $this->reward_amount
            - (float) $this->absence_deduction
            - (float) $this->late_deduction
            - (float) $this->cash_advance_deduction
            - (float) $this->device_deduction
            - (float) $this->other_deductions,
            2,
        );
    }

    protected function hourlyRate(): Attribute
    {
        return Attribute::make(get: fn () => self::decimal($this->rate ? $this->rate->hourlyRate() : 0, 4), set: fn () => []);
    }

    protected function dailyRate(): Attribute
    {
        return Attribute::make(get: fn () => self::decimal($this->rate ? $this->rate->dailyRate() : 0), set: fn () => []);
    }

    protected function additionalHours(): Attribute
    {
        return Attribute::make(get: fn () => self::decimal((int) $this->additional_minutes / 60), set: fn () => []);
    }

    protected function lateHours(): Attribute
    {
        return Attribute::make(get: fn () => self::decimal((int) $this->late_minutes / 60), set: fn () => []);
    }

    /**
     * A calculated amount in the same form as a decimal column: "1234.50".
     */
    private static function decimal(mixed $value, int $places = 2): string
    {
        return number_format(round((float) $value, $places), $places, '.', '');
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    /**
     * Cash rewards paid on this row as Additional Pay (payroll_rewards).
     *
     * @return BelongsToMany<Reward, $this>
     */
    public function rewards(): BelongsToMany
    {
        return $this->belongsToMany(Reward::class, 'payroll_rewards', 'payroll_id', 'reward_id');
    }

    /**
     * Approved overtime paid on this row (payroll_overtime).
     *
     * @return BelongsToMany<OvertimeRequest, $this>
     */
    public function overtimeRequests(): BelongsToMany
    {
        return $this->belongsToMany(OvertimeRequest::class, 'payroll_overtime', 'payroll_id', 'overtime_request_id');
    }

    /**
     * @return HasMany<CashAdvanceRepayment, $this>
     */
    public function cashAdvanceRepayments(): HasMany
    {
        return $this->hasMany(CashAdvanceRepayment::class);
    }

    /**
     * @return HasMany<DeviceDeduction, $this>
     */
    public function deviceDeductions(): HasMany
    {
        return $this->hasMany(DeviceDeduction::class);
    }

    /**
     * @return BelongsTo<Rate, $this>
     */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }
}
