<?php

namespace App\Models;

use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Models\Concerns\BelongsToHomeOffice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['period_name', 'start_date', 'end_date', 'cutoff_date', 'release_date', 'pay_frequency', 'status', 'verification_opened_at', 'admin_submitted_at', 'last_reminded_at', 'last_reminded_count'])]
class PayrollPeriod extends Model
{
    use BelongsToHomeOffice, HasFactory;

    /**
     * The activity log action written when the Admin submits the verified period.
     */
    public const SUBMITTED_ACTION = 'Submitted verified period to the Super Admin';

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
            'admin_submitted_at' => 'immutable_datetime',
            'last_reminded_at' => 'immutable_datetime',
            'last_reminded_count' => 'integer',
        ];
    }

    /**
     * The Admin who reviewed the contractors' changes and submitted the verified period.
     */
    protected function adminSubmitter(): Attribute
    {
        // Who submitted is read from the activity log, so the period keeps no *_by column.
        return Attribute::get(fn () => $this->admin_submitted_at === null ? null : ActivityLog::query()
            ->where('reference_table', $this->getTable())
            ->where('reference_id', $this->id)
            ->where('action', self::SUBMITTED_ACTION)
            ->latest('id')
            ->first()?->user);
    }

    /**
     * The Super Admin can process payroll only after the Admin submitted the verified period.
     */
    public function isSubmittedByAdmin(): bool
    {
        return $this->admin_submitted_at !== null;
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
