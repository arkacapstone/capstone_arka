<?php

namespace App\Models;

use App\Enums\TimeLogStatus;
use App\Models\Concerns\BelongsToAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One timer session for one client (Contractor flow §III). Several can run at once;
 * together they produce the day's attendance record for each client.
 *
 * Nothing calculated is stored: the status comes from time_out and break_started_at, and the
 * total hours from the times. The schedule that applies is the assignment's schedule that day.
 */
#[Fillable(['assignment_id', 'employee_id', 'client_id', 'date', 'time_in', 'time_out', 'break_minutes', 'break_started_at'])]
class TimeLog extends Model
{
    use BelongsToAssignment, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'time_in' => 'immutable_datetime',
            'time_out' => 'immutable_datetime',
            'break_started_at' => 'immutable_datetime',
            'break_minutes' => 'integer',
        ];
    }

    /**
     * Completed once stopped, on break while a break is running, otherwise running.
     */
    protected function status(): Attribute
    {
        return Attribute::make(set: fn () => [], get: fn () => match (true) {
            $this->time_out !== null => TimeLogStatus::Completed,
            $this->break_started_at !== null => TimeLogStatus::OnBreak,
            default => TimeLogStatus::Running,
        });
    }

    /**
     * Hours worked, excluding breaks. Null while the timer is still running.
     */
    protected function totalHours(): Attribute
    {
        return Attribute::make(get: fn () => $this->time_out === null ? null : number_format($this->workedSecondsAt($this->time_out) / 3600, 2, '.', ''), set: fn () => []);
    }

    /**
     * The schedule that applied to this session: the assignment's schedule on the work date.
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
     * Timers that were started but have not been stopped yet.
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('time_out'));
    }

    /**
     * Time logs in this state: completed (stopped), on break (break running) or running.
     */
    #[Scope]
    protected function withStatus(Builder $query, TimeLogStatus $status): void
    {
        match ($status) {
            TimeLogStatus::Completed => $query->whereNotNull($this->qualifyColumn('time_out')),
            TimeLogStatus::OnBreak => $query->whereNull($this->qualifyColumn('time_out'))->whereNotNull($this->qualifyColumn('break_started_at')),
            TimeLogStatus::Running => $query->whereNull($this->qualifyColumn('time_out'))->whereNull($this->qualifyColumn('break_started_at')),
        };
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
