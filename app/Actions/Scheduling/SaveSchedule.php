<?php

namespace App\Actions\Scheduling;

use App\Enums\EmploymentType;
use App\Enums\Weekday;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleChanged;
use App\Notifications\ScheduleOverlapDetected;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\Scheduling\ScheduleOverlapDetector;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates schedules and applies changes without erasing history (Blueprint §6):
 * a change that starts after the current period began ends the old schedule the
 * day before and creates a new applicable period.
 */
class SaveSchedule
{
    public function __construct(
        private readonly ScheduleOverlapDetector $overlaps,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
        private readonly SystemRules $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{schedule: Schedule, overlaps: Collection<int, Schedule>}
     */
    public function create(array $attributes): array
    {
        $this->ensureClientAssigned($attributes);
        $this->ensureWorkWeekLimit($attributes['employee_id'], $attributes['working_days'], CarbonImmutable::parse($attributes['start_date']), $this->endDate($attributes));
        $attributes = $this->withShiftLength($attributes);

        $schedule = Schedule::create([...$attributes, 'status' => Schedule::STATUS_ACTIVE]);

        $this->activity->log('scheduling', 'Created schedule', $schedule, $this->describe($schedule));
        $schedule->employee->notify(new ScheduleChanged($schedule, created: true));

        return $this->withOverlaps($schedule, $this->overlaps->overlapping($attributes, $schedule->id));
    }

    /**
     * @param  array<string, mixed>  $attributes  includes `effective_date`
     * @return array{schedule: Schedule, overlaps: Collection<int, Schedule>}
     */
    public function change(Schedule $current, array $attributes): array
    {
        $effective = CarbonImmutable::parse($attributes['effective_date']);
        unset($attributes['effective_date']);
        $attributes['employee_id'] = $current->employee_id;

        $this->ensureClientAssigned($attributes);
        // The schedule being changed is replaced from the effective date, so it never counts against the limit.
        $this->ensureWorkWeekLimit($current->employee_id, $attributes['working_days'], $effective->max($current->start_date), $this->endDate($attributes), ignore: $current);
        $attributes = $this->withShiftLength($attributes);

        $schedule = DB::transaction(function () use ($current, $attributes, $effective) {
            // Not started yet (or starts the same day): nothing to preserve, edit in place.
            if ($effective->lessThanOrEqualTo($current->start_date)) {
                $current->update($attributes);

                return $current;
            }

            $current->update(['end_date' => $effective->subDay()]);

            return Schedule::create([
                ...$attributes,
                'start_date' => $effective,
                'end_date' => $attributes['end_date'] ?? null,
                'status' => Schedule::STATUS_ACTIVE,
            ]);
        });

        $this->activity->log('scheduling', 'Changed schedule', $schedule, $this->describe($schedule));
        $schedule->employee->notify(new ScheduleChanged($schedule, created: false));

        return $this->withOverlaps($schedule, $this->overlaps->overlapping([...$attributes, 'start_date' => $schedule->start_date->toDateString()], $schedule->id));
    }

    /**
     * Overlaps are allowed (two part-time clients), but the other Admins get a heads-up.
     *
     * @param  Collection<int, Schedule>  $overlaps
     * @return array{schedule: Schedule, overlaps: Collection<int, Schedule>}
     */
    private function withOverlaps(Schedule $schedule, Collection $overlaps): array
    {
        if ($overlaps->isNotEmpty()) {
            $this->notifier->admins(new ScheduleOverlapDetected($schedule, $overlaps->count()), except: Auth::user());
        }

        return ['schedule' => $schedule, 'overlaps' => $overlaps];
    }

    public function setStatus(Schedule $schedule, string $status): Schedule
    {
        $schedule->update(['status' => $status]);

        $verb = $status === Schedule::STATUS_ACTIVE ? 'Activated' : 'Deactivated';
        $this->activity->log('scheduling', "{$verb} schedule", $schedule, $this->describe($schedule));

        return $schedule;
    }

    /**
     * A schedule follows a client assignment the Super Admin approved with a rate (Blueprint §4, §5).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function ensureClientAssigned(array $attributes): void
    {
        $assigned = User::find($attributes['employee_id'])?->currentRates()->where('client_id', $attributes['client_id'])->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'client_id' => 'This contractor is not assigned to that client yet. The Super Admin approves client assignments first.',
            ]);
        }
    }

    /**
     * A contractor works at most five days a week across all their clients: the working days of
     * this schedule plus those of every other active schedule in effect at the same time.
     *
     * @param  list<string>  $workingDays
     */
    private function ensureWorkWeekLimit(int $employeeId, array $workingDays, CarbonImmutable $from, ?CarbonImmutable $to, ?Schedule $ignore = null): void
    {
        $others = Schedule::query()
            ->with('client:id,client_name')
            ->where('employee_id', $employeeId)
            ->active()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->when($to, fn ($query) => $query->whereDate('start_date', '<=', $to))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->get();

        $days = collect($workingDays)->merge($others->flatMap(fn (Schedule $schedule) => $schedule->working_days ?? []))->unique();

        if ($days->count() <= Schedule::MAX_WORKING_DAYS) {
            return;
        }

        $existing = $others->map(fn (Schedule $schedule) => $schedule->client->client_name.' ('.collect($schedule->working_days)->map(fn (string $day) => Weekday::from($day)->short())->implode(', ').')')->implode('; ');

        throw ValidationException::withMessages([
            'working_days' => 'A contractor can be scheduled for at most '.Schedule::MAX_WORKING_DAYS." working days a week. This would make {$days->count()} days, together with: {$existing}.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function endDate(array $attributes): ?CarbonImmutable
    {
        return filled($attributes['end_date'] ?? null) ? CarbonImmutable::parse($attributes['end_date']) : null;
    }

    /**
     * A Full-Time or Part-Time client sets the schedule length (System & Rules), so the end time
     * follows from the start time.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withShiftLength(array $attributes): array
    {
        $type = User::find($attributes['employee_id'])?->currentRates()->where('client_id', $attributes['client_id'])->first()?->employment_type;
        $hours = match ($type) {
            EmploymentType::FullTime => $this->rules->integer('full_time_hours'),
            EmploymentType::PartTime => $this->rules->integer('part_time_hours'),
            default => null,
        };

        if ($hours !== null) {
            $attributes['end_time'] = CarbonImmutable::createFromFormat('H:i', substr($attributes['start_time'], 0, 5))->addHours($hours)->format('H:i');
        }

        return $attributes;
    }

    private function describe(Schedule $schedule): string
    {
        $schedule->loadMissing('employee:id,name', 'client:id,client_name');

        return "{$schedule->employee->name} · {$schedule->client->client_name} · ".substr($schedule->start_time, 0, 5).'–'.substr($schedule->end_time, 0, 5);
    }
}
