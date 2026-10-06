<?php

namespace App\Models;

use App\Enums\TimeLogStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One timer session for one client (Contractor flow §III). Several can run at once;
 * together they produce the day's attendance record for each client.
 */
#[Fillable(['employee_id', 'client_id', 'schedule_id', 'date', 'time_in', 'time_out', 'break_minutes', 'break_started_at', 'total_hours', 'status'])]
class TimeLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'time_in' => 'immutable_datetime',
            'time_out' => 'immutable_datetime',
            'break_started_at' => 'immutable_datetime',
            'total_hours' => 'decimal:2',
            'status' => TimeLogStatus::class,
            'break_minutes' => 'integer',
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Timers that were started but have not been stopped yet.
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('time_out');
    }

    public function isOpen(): bool
    {
        return $this->time_out === null;
    }

    /**
     * Break minutes used so far, including a break that is still running.
     */
    public function breakMinutesAt(CarbonInterface $moment): int
    {
        $current = $this->break_started_at ? (int) $this->break_started_at->diffInMinutes($moment) : 0;

        return (int) $this->break_minutes + $current;
    }

    /**
     * Worked seconds, excluding breaks, up to the moment (or the time-out once stopped).
     */
    public function workedSecondsAt(CarbonInterface $moment): int
    {
        $end = $this->time_out ?? CarbonImmutable::instance($moment);
        $breakSeconds = (int) $this->break_minutes * 60
            + ($this->break_started_at ? (int) $this->break_started_at->diffInSeconds($end) : 0);

        return max(0, (int) $this->time_in->diffInSeconds($end) - $breakSeconds);
    }
}
