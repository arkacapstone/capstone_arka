<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attendance_id', 'employee_id', 'verification_id', 'date', 'source', 'field_corrected', 'original_time_in', 'original_time_out', 'requested_time_in', 'requested_time_out', 'reason', 'proof_path', 'status', 'reviewed_by', 'reviewed_at', 'admin_remarks'])]
class AttendanceCorrection extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => CorrectionStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', CorrectionStatus::Pending);
    }
}
