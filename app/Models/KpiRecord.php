<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An approved performance evaluation (Blueprint §3.1 Performance & Rewards). Devotional compliance
 * may count as a positive factor here — it never becomes a payroll deduction.
 */
#[Fillable(['employee_id', 'period_id', 'kpi_score', 'remarks', 'evaluated_by', 'evaluated_at'])]
class KpiRecord extends Model
{
    protected function casts(): array
    {
        return [
            'kpi_score' => 'decimal:2',
            'evaluated_at' => 'datetime',
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
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'kpi_id');
    }

    /**
     * Excellent 90+, Very good 80+, Good 70+, Needs support below.
     */
    public function rating(): string
    {
        $score = (float) $this->kpi_score;

        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 80 => 'Very good',
            $score >= 70 => 'Good',
            default => 'Needs support',
        };
    }
}
