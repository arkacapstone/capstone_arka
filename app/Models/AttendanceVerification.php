<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['period_id', 'employee_id', 'verified_at'])]
class AttendanceVerification extends Model
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Every day the contractor fixed while verifying, oldest first.
     *
     * @return HasMany<AttendanceCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'verification_id')->oldest('id');
    }
}
