<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * The day's result for one contractor and client (one client assignment).
 *
 * Time in, time out, break and actual hours are not stored: they come from that day's
 * finished time logs (the attendance_summary view does the same in SQL). Setting time_in /
 * time_out here moves the day's first / last time log instead (or starts one).
 * Late and undertime are snapshots computed at the time.
 */
#[Fillable(['assignment_id', 'employee_id', 'client_id', 'date', 'time_in', 'time_out', 'break_minutes', 'late_minutes', 'undertime_minutes', 'status', 'leave_request_id'])]
class Attendance extends Model
{
    use BelongsToAssignment, HasFactory;

    /**
     * Times set on the attendance, written to the day's time logs when it is saved.
     *
     * @var array<string, mixed>
     */
    protected array $pendingTimes = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Attendance $attendance) {
            if ($attendance->pendingTimes !== []) {
                $attendance->writeTimesToLogs();
            }
        });
    }

    /**
     * Every time log of this assignment; the day's are picked by date (see dayLogs()).
     * Eager load it on lists to avoid one query per row.
     *
     * @return HasMany<TimeLog, $this>
     */
    public function assignmentTimeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class, 'assignment_id', 'assignment_id');
    }

    /**
     * This day's time logs, oldest first: all of them, or only the finished ones.
     *
     * @return Collection<int, TimeLog>
     */
    public function dayLogs(bool $finishedOnly = true): Collection
    {
        $date = $this->date?->toDateString();

        return $this->assignmentTimeLogs
            ->filter(fn (TimeLog $log) => (! $finishedOnly || $log->time_out !== null) && $log->date->toDateString() === $date)
            ->sortBy('time_in')
            ->values();
    }

    protected function timeIn(): Attribute
    {
        // The first session's start, even while a timer is still running.
        return $this->timeAttribute('time_in', fn () => $this->dayLogs(finishedOnly: false)->first()?->time_in);
    }

    protected function timeOut(): Attribute
    {
        // No time out while any session of the day is still running.
        return $this->timeAttribute('time_out', function () {
            $logs = $this->dayLogs(finishedOnly: false);

            return $logs->isEmpty() || $logs->contains(fn (TimeLog $log) => $log->isOpen()) ? null : $logs->max('time_out');
        });
    }

    protected function breakMinutes(): Attribute
    {
        return $this->timeAttribute('break_minutes', fn () => (int) $this->dayLogs(finishedOnly: false)->sum('break_minutes'));
    }

    /**
     * Hours worked that day, excluding breaks.
     */
    protected function actualHours(): Attribute
    {
        return Attribute::make(set: fn () => [], get: function () {
            $logs = $this->dayLogs();

            if ($logs->isEmpty()) {
                return null;
            }

            // Separate sessions count only the time actually tracked, not the gaps between them.
            // Time before the scheduled start (the timer may start a few minutes early) is not counted.
            $schedule = $this->schedule;
            $shiftStart = $schedule ? CarbonImmutable::instance($schedule->windowOn($this->date)[0]) : null;

            return number_format($logs->sum(function (TimeLog $log) use ($shiftStart) {
                $early = $shiftStart && $log->time_in->lessThan($shiftStart) ? $log->time_in->diffInSeconds($log->time_out->min($shiftStart)) : 0;

                return max(0, $log->workedSecondsAt($log->time_out) - $early);
            }) / 3600, 2, '.', '');
        });
    }

    private function timeAttribute(string $field, callable $fromLogs): Attribute
    {
        return Attribute::make(
            get: function () use ($field, $fromLogs) {
                if (! array_key_exists($field, $this->pendingTimes)) {
                    return $fromLogs();
                }

                $value = $this->pendingTimes[$field];

                return $field === 'break_minutes' || $value === null ? $value : CarbonImmutable::parse($value);
            },
            set: function ($value) use ($field) {
                $this->pendingTimes[$field] = $value;

                return [];
            },
        );
    }

    /**
     * Moves the day's first time-in and last time-out to the times set here, or starts a time
     * log for the day when there is none yet.
     */
    private function writeTimesToLogs(): void
    {
        $times = $this->pendingTimes;
        $this->pendingTimes = [];
        $this->unsetRelation('assignmentTimeLogs');

        $logs = TimeLog::query()->where('assignment_id', $this->assignment_id)->whereDate('date', $this->date)->orderBy('time_in')->get();

        if ($logs->isEmpty()) {
            if (($times['time_in'] ?? null) === null) {
                return;
            }

            TimeLog::query()->create([
                'assignment_id' => $this->assignment_id,
                'date' => $this->date->toDateString(),
                'time_in' => $times['time_in'],
                'time_out' => $times['time_out'] ?? null,
                'break_minutes' => (int) ($times['break_minutes'] ?? 0),
            ]);

            return;
        }

        if (array_key_exists('time_in', $times) && $times['time_in'] !== null) {
            $logs->first()->update(['time_in' => $times['time_in']]);
        }

        if (array_key_exists('time_out', $times)) {
            // A missing clock-out is fixed on the session still running, otherwise on the last one.
            ($logs->first(fn (TimeLog $log) => $log->isOpen()) ?? $logs->last())->update(['time_out' => $times['time_out']]);
        }

        if (array_key_exists('break_minutes', $times) && $logs->count() === 1) {
            $logs->first()->update(['break_minutes' => (int) $times['break_minutes']]);
        }
    }

    /**
     * The schedule that applied that day: the assignment's schedule on the date.
     */
    protected function schedule(): Attribute
    {
        return Attribute::get(fn () => Schedule::query()
            ->where('assignment_id', $this->assignment_id)
            ->active()
            ->applicableOn($this->date)
            ->latest('start_date')
            ->first())->shouldCache();
    }

    /**
     * The approved leave this day was taken under (paid or unpaid leave days).
     *
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * The leave type comes from the leave request.
     */
    protected function leaveType(): Attribute
    {
        return Attribute::get(fn () => $this->leaveRequest?->leaveType);
    }

    /**
     * Loads how many fix requests each day has (corrections_count), for lists.
     */
    #[Scope]
    protected function withCorrectionCount(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->addSelect(['corrections_count' => AttendanceCorrection::query()
            ->selectRaw('count(*)')
            ->whereColumn('attendance_corrections.assignment_id', 'attendances.assignment_id')
            ->whereColumn('attendance_corrections.date', 'attendances.date')]);
    }

    /**
     * Fix requests for this contractor, client and date (found by assignment and date).
     * Use on one attendance; it is not meant for eager loading.
     *
     * @return HasMany<AttendanceCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'assignment_id', 'assignment_id')->whereDate('date', $this->date);
    }
}
