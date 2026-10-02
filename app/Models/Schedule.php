<?php

namespace App\Models;

use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client-specific work schedule for one employee (Blueprint §6).
 *
 * A change never erases a schedule: it ends the current one and starts a new
 * applicable period, so attendance is always compared with the schedule in effect that day.
 */
#[Fillable(['employee_id', 'client_id', 'work_schedule_id', 'job_position', 'working_days', 'start_time', 'end_time', 'break_allowance_minutes', 'start_date', 'end_date', 'status'])]
class Schedule extends Model
{
    use HasFactory;

    /**
     * Break minutes per session when the Admin does not set one. Informational only: going over never reduces pay.
     */
    public const DEFAULT_BREAK_ALLOWANCE = 60;

    /**
     * Most working days a contractor can be scheduled for in a week, across all their clients.
     */
    public const MAX_WORKING_DAYS = 5;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Everyone is a contractor doing general work, so there is no job position to choose.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['job_position' => 'Contractor'];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'break_allowance_minutes' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    /**
     * Stored as HH:MM:SS whatever the input format, so time comparisons stay consistent.
     */
    protected function startTime(): Attribute
    {
        return Attribute::set(fn (string $value) => self::normalizeTime($value));
    }

    protected function endTime(): Attribute
    {
        return Attribute::set(fn (string $value) => self::normalizeTime($value));
    }

    private static function normalizeTime(string $value): string
    {
        return strlen($value) === 5 ? "{$value}:00" : $value;
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

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Schedules whose applicable period includes the date (ignores working days).
     */
    #[Scope]
    protected function applicableOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }

    public function worksOn(CarbonInterface $date): bool
    {
        return in_array(Weekday::of($date)->value, $this->working_days ?? [], true);
    }

    /**
     * Graveyard shifts end on the next calendar day (e.g. 10:00 PM – 6:00 AM).
     */
    public function crossesMidnight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    /**
     * The scheduled start and end for a given schedule date, treated as one work period
     * even when the shift crosses midnight (Blueprint §7).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function windowOn(CarbonInterface $date): array
    {
        $day = CarbonImmutable::parse($date->toDateString());
        $start = $day->setTimeFromTimeString($this->start_time);
        $end = $day->setTimeFromTimeString($this->end_time);

        return [$start, $this->crossesMidnight() ? $end->addDay() : $end];
    }

    public function expectedHours(): float
    {
        [$start, $end] = $this->windowOn(CarbonImmutable::today());

        return round($start->diffInMinutes($end) / 60, 2);
    }

    /**
     * Whether the contractor is scheduled to be working at this moment,
     * including a graveyard shift that started the previous day.
     */
    public function isOnShiftAt(CarbonInterface $moment): bool
    {
        foreach ([$moment->copy()->subDay(), $moment] as $date) {
            if (! $this->worksOn($date) || ! $this->appliesOn($date)) {
                continue;
            }

            [$start, $end] = $this->windowOn($date);

            if ($moment->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    public function appliesOn(CarbonInterface $date): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->start_date->lessThanOrEqualTo($date->copy()->startOfDay())
            && ($this->end_date === null || $this->end_date->greaterThanOrEqualTo($date->copy()->startOfDay()));
    }
}
