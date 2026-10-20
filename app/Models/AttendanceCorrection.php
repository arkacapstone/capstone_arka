<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use App\Models\Concerns\BelongsToAssignment;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fix request for one client assignment on one date. The attendance row, if there is one,
 * is found by the same assignment and date, so no attendance_id is stored. original_* keep
 * the times before the fix, so the change stays auditable.
 */
#[Fillable(['assignment_id', 'employee_id', 'client_id', 'attendance_id', 'verification_id', 'date', 'source', 'field_corrected', 'original_time_in', 'original_time_out', 'requested_time_in', 'requested_time_out', 'reason', 'proof_path', 'status', 'reviewed_by', 'reviewed_at', 'admin_remarks'])]
class AttendanceCorrection extends Model
{
    use BelongsToAssignment, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => CorrectionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected function originalTimeIn(): Attribute
    {
        return $this->timeOfDay();
    }

    protected function originalTimeOut(): Attribute
    {
        return $this->timeOfDay();
    }

    protected function requestedTimeIn(): Attribute
    {
        return $this->timeOfDay();
    }

    protected function requestedTimeOut(): Attribute
    {
        return $this->timeOfDay();
    }

    /**
     * The four time columns are DATETIME: a time like "09:00" is saved on the request's date,
     * and read back as "09:00".
     */
    private function timeOfDay(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : CarbonImmutable::parse($value)->format('H:i'),
            set: function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }

                if ($value instanceof DateTimeInterface) {
                    return CarbonImmutable::instance($value)->format('Y-m-d H:i:s');
                }

                $date = substr((string) ($this->attributes['date'] ?? now()->toDateString()), 0, 10);

                return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)
                    ? CarbonImmutable::parse("{$date} {$value}")->format('Y-m-d H:i:s')
                    : CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
            },
        );
    }

    /**
     * The attendance for this assignment and date. Setting an attendance copies its assignment
     * and date onto the request.
     */
    protected function attendanceId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance?->id,
            set: function ($value) {
                $attendance = $value === null ? null : Attendance::query()->find($value);

                return $attendance === null ? [] : [
                    'assignment_id' => $attendance->assignment_id,
                    'date' => $attendance->date->toDateString(),
                ];
            },
        );
    }

    protected function attendance(): Attribute
    {
        return Attribute::get(fn () => $this->assignment_id === null || $this->date === null ? null : Attendance::query()
            ->where('assignment_id', $this->assignment_id)
            ->whereDate('date', $this->date)
            ->first());
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
