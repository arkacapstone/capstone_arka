<?php

namespace App\Models;

use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['period_name', 'start_date', 'end_date', 'cutoff_date', 'release_date', 'pay_frequency', 'status', 'verification_opened_at'])]
class PayrollPeriod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'cutoff_date' => 'immutable_date',
            'release_date' => 'immutable_date',
            'pay_frequency' => PayFrequency::class,
            'status' => PayrollPeriodStatus::class,
            'verification_opened_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Payroll, $this>
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'period_id');
    }

    /**
     * @return HasMany<AttendanceVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(AttendanceVerification::class, 'period_id');
    }

    /**
     * Contractors may fix their attendance only on the day the Super Admin opened verification, until midnight.
     */
    public function fixDeadline(): ?CarbonImmutable
    {
        return $this->verification_opened_at?->endOfDay();
    }

    public function fixWindowOpen(?CarbonImmutable $now = null): bool
    {
        $deadline = $this->fixDeadline();

        return $this->status === PayrollPeriodStatus::Verification
            && $deadline !== null
            && ($now ?? CarbonImmutable::now())->lessThanOrEqualTo($deadline);
    }

    #[Scope]
    protected function covering(Builder $query, CarbonImmutable $date): void
    {
        $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
    }
}
