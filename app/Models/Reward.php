<?php

namespace App\Models;

use App\Models\Concerns\PaidInPayroll;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A reward earned from a KPI record: cash (added to a payslip, e.g. a bonus) or non-cash
 * (e.g. recognition). The contractor is not stored here: it is the KPI record's. A cash
 * reward's payroll line is kept in payroll_rewards (see PaidInPayroll).
 */
#[Fillable(['kpi_id', 'reward_type', 'amount', 'description', 'awarded_by', 'awarded_at', 'payroll_id'])]
class Reward extends Model
{
    use PaidInPayroll;

    public const PAYROLL_JUNCTION = PayrollReward::class;

    public const PAYROLL_KEY = 'reward_id';

    public const CASH = 'cash';

    public const NON_CASH = 'non_cash';

    /**
     * Kinds of reward offered when awarding one; the kind is written into the description.
     */
    public const TYPES = ['Bonus', 'Incentive', 'Recognition', 'Promotion recommendation'];

    protected static function booted(): void
    {
        // Only the rewards of KPI records of contractors in the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('kpi_id'), DB::table('kpi_records')->select('id')->whereIn('contractor_id', HomeOffice::userIds($homeOfficeId)));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KpiRecord, $this>
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(KpiRecord::class, 'kpi_id');
    }

    /**
     * The contractor's account, through the KPI record (contractor_id is the user's id).
     *
     * @return HasOneThrough<User, KpiRecord, $this>
     */
    public function employee(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, KpiRecord::class, 'id', 'id', 'kpi_id', 'contractor_id');
    }

    protected function employeeId(): Attribute
    {
        return Attribute::get(fn () => $this->kpi?->contractor_id);
    }

    /**
     * The kind of reward (Bonus, Recognition, …): the start of the description.
     */
    public function kind(): string
    {
        return Str::before((string) $this->description, ' — ');
    }

    /**
     * What was written about it, after the kind, if anything.
     */
    public function note(): ?string
    {
        return str_contains((string) $this->description, ' — ') ? Str::after($this->description, ' — ') : null;
    }

    public function isCash(): bool
    {
        return $this->reward_type === self::CASH;
    }

    /**
     * Rewards of these contractors (user ids), through their KPI records.
     */
    #[Scope]
    protected function forEmployee(Builder $query, mixed $employees): void
    {
        $query->whereIn('kpi_id', KpiRecord::query()->select('id')->whereIn('contractor_id', is_int($employees) ? [$employees] : $employees));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
