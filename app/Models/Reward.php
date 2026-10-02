<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reward, incentive or recognition, optionally tied to a KPI evaluation.
 */
#[Fillable(['employee_id', 'kpi_id', 'reward_type', 'amount', 'description', 'awarded_by', 'awarded_at', 'payroll_id'])]
class Reward extends Model
{
    public const TYPES = ['Bonus', 'Incentive', 'Recognition', 'Promotion recommendation'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'awarded_at' => 'datetime',
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
     * @return BelongsTo<KpiRecord, $this>
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(KpiRecord::class, 'kpi_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
