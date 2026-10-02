<?php

namespace App\Actions\TimeTracking;

use App\Enums\TimeLogStatus;
use App\Models\Schedule;
use App\Models\TimeLog;
use App\Models\User;
use App\Notifications\TimerStoppedAtShiftEnd;
use App\Services\Attendance\AttendanceLock;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Settings\SystemRules;
use App\Services\TimeTracking\AssignedClients;
use App\Services\TimeTracking\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Per-client timers (Contractor flow §III): start, pause for a break, resume and stop.
 * Several clients can run at once; every change updates that client's attendance.
 */
class ManageTimer
{
    public function __construct(
        private readonly AssignedClients $clients,
        private readonly WorkDate $workDate,
        private readonly AttendanceLock $lock,
        private readonly SyncAttendance $sync,
        private readonly ScheduleResolver $schedules,
        private readonly SystemRules $rules,
    ) {}

    public function start(User $employee, int $clientId, ?CarbonImmutable $now = null): TimeLog
    {
        $now ??= CarbonImmutable::now();

        if (! $this->clients->includes($employee, $clientId, $now)) {
            throw ValidationException::withMessages(['client_id' => "You're not assigned to that client yet. Your administrator sets up client assignments."]);
        }

        if ($employee->timeLogs()->open()->where('client_id', $clientId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'A timer is already running for this client.']);
        }

        [$date, $schedule] = $this->workDate->resolve($employee, $clientId, $now);
        $this->ensureWithinShift($date, $schedule, $now);

        if ($this->lock->isLocked($date)) {
            throw ValidationException::withMessages(['client_id' => "{$date->format('M j')} is in a payroll period that is already locked or released, so no new time can be tracked for it. Contact the Super Admin if this is a mistake."]);
        }

        $log = DB::transaction(fn () => $employee->timeLogs()->create([
            'client_id' => $clientId,
            'schedule_id' => $schedule?->id,
            'date' => $date,
            'time_in' => $now,
            'break_minutes' => 0,
            'status' => TimeLogStatus::Running,
        ]));

        $this->sync->handle($employee->id, $clientId, $date);

        return $log;
    }

    /**
     * A timer can be started only during the shift: from a few minutes before it starts (System & Rules)
     * until it ends. Time after the shift is paid through an overtime ticket, never the timer.
     */
    private function ensureWithinShift(CarbonImmutable $date, ?Schedule $schedule, CarbonImmutable $now): void
    {
        if ($schedule === null) {
            throw ValidationException::withMessages(['client_id' => 'You have no shift for this client today, so the timer cannot be started. Your administrator sets your schedule.']);
        }

        [$start, $end] = array_map(fn ($time) => CarbonImmutable::instance($time), $schedule->windowOn($date));
        $opens = $start->subMinutes($this->rules->integer('timer_early_start_minutes'));

        if ($now->lessThan($opens)) {
            throw ValidationException::withMessages(['client_id' => "Your shift starts at {$start->format('g:i A')}. You can start the timer from {$opens->format('g:i A')}."]);
        }

        if ($now->greaterThanOrEqualTo($end)) {
            throw ValidationException::withMessages(['client_id' => "Your shift ended at {$end->format('g:i A')}. Worked overtime approved by your client handler? File an overtime ticket instead."]);
        }
    }

    /**
     * Break ↔ Resume. Break time is informational only — going over never reduces pay.
     */
    public function toggleBreak(TimeLog $log, ?CarbonImmutable $now = null): TimeLog
    {
        $now ??= CarbonImmutable::now();
        $this->ensureOpen($log);

        if ($log->status === TimeLogStatus::OnBreak) {
            $log->update([
                'break_minutes' => $log->breakMinutesAt($now),
                'break_started_at' => null,
                'status' => TimeLogStatus::Running,
            ]);
        } else {
            $log->update(['break_started_at' => $now, 'status' => TimeLogStatus::OnBreak]);
        }

        return $log;
    }

    public function stop(TimeLog $log, ?CarbonImmutable $now = null): TimeLog
    {
        $now ??= CarbonImmutable::now();
        $this->ensureOpen($log);

        $log->fill([
            'break_minutes' => $log->breakMinutesAt($now),
            'break_started_at' => null,
            'time_out' => $now,
            'status' => TimeLogStatus::Completed,
        ]);
        $log->total_hours = round($log->workedSecondsAt($now) / 3600, 2);
        $log->save();

        $this->sync->handle($log->employee_id, $log->client_id, $log->date);

        return $log;
    }

    public function stopAll(User $employee, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $open = $employee->timeLogs()->open()->get();

        $open->each(fn (TimeLog $log) => $this->stop($log, $now));

        return $open->count();
    }

    /**
     * Timers still running when their shift ends stop at the scheduled end time, so a forgotten
     * timer never keeps counting. Overtime is paid through an overtime ticket, not the timer.
     * (A timer cannot be started after the shift ends, see ensureWithinShift.)
     *
     * @return int how many timers were stopped
     */
    public function stopFinishedShifts(?User $employee = null, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $stopped = 0;

        $open = TimeLog::query()->open()
            ->when($employee, fn ($query) => $query->where('employee_id', $employee->id))
            ->with('client:id,client_name')
            ->get();

        foreach ($open as $log) {
            $schedule = $this->schedules->for($log->employee_id, $log->client_id, $log->date);

            if ($schedule === null) {
                continue;
            }

            [, $end] = $schedule->windowOn($log->date);
            $end = CarbonImmutable::instance($end);

            if ($now->lessThan($end) || $log->time_in->greaterThanOrEqualTo($end)) {
                continue;
            }

            $this->stop($log, $end);
            $log->employee->notify(new TimerStoppedAtShiftEnd($log));
            $stopped++;
        }

        return $stopped;
    }

    private function ensureOpen(TimeLog $log): void
    {
        if (! $log->isOpen()) {
            throw ValidationException::withMessages(['timer' => 'This timer has already stopped.']);
        }
    }
}
