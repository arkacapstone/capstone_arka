<?php

namespace App\Actions\Scheduling;

use App\Enums\EmploymentType;
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
