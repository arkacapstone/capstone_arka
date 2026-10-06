<?php

namespace App\Services\TimeTracking;

use App\Models\Client;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\Attendance\AttendanceLock;
use App\Services\Attendance\FixHistory;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Everything the Time Tracker shows (Contractor flow §III): one card per client with its live
 * timer, the combined running total, and today's sessions. The dashboard reuses it.
 *
 * Time on a day whose payroll is already locked or released has been paid out, so the timers
 * start again from zero; those sessions stay in the summary and in Time History.
 */
class TimerBoard
{
    private const LONG_RUNNING_SECONDS = 3 * 3600;

    /** @var array<string, bool> */
    private array $lockedDays = [];

    public function __construct(
        private readonly AssignedClients $assigned,
        private readonly AttendanceLock $lock,
        private readonly FixHistory $fixes,
        private readonly SystemRules $rules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $employee, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $clients = $this->assigned->for($employee, $now);

        // Today's sessions, plus any timer still open from an earlier day (e.g. an overnight shift).
        $logs = $employee->timeLogs()
            ->with('client:id,client_name')
            ->where(fn ($query) => $query->whereDate('date', '>=', $now->subDay()->toDateString())->orWhereNull('time_out'))
            ->orderBy('time_in')
            ->get();

        // Finished sessions already in a locked or released payroll no longer count on the timers.
        $counted = $logs->reject(fn (TimeLog $log) => $this->paidOut($log));
        $rates = $employee->currentRates()->get(['client_id', 'employment_type'])->keyBy('client_id');

        $cards = $clients->map(fn (array $row) => $this->card($employee, $row, $counted, $now, $rates->get($row['client']->id)))->values();
        $open = $logs->filter(fn (TimeLog $log) => $log->isOpen());
        $isToday = fn (TimeLog $log) => $log->isOpen() || $log->date->isSameDay($now) || $log->time_in->isSameDay($now);
        $todays = $logs->filter($isToday);
        // The combined total follows the cards: finished shifts no longer count.
        $cardSeconds = $cards->sum(fn (array $card) => $card['completedSeconds'] + ($card['timer']['workedSeconds'] ?? 0));

        return [
            'serverNow' => $now->toIso8601String(),
            'clients' => $cards->all(),
            'running' => $open->count(),
            'totalClients' => $cards->count(),
            'combinedSeconds' => $cardSeconds,
            'locked' => $this->isLocked($now->startOfDay()),
            'scheduledMinutes' => (int) round($clients->sum(fn (array $row) => $row['schedule'] && $row['schedule']->worksOn($row['date']) ? $row['schedule']->expectedHours() * 60 : 0)),
            'today' => $todays->values()->map(fn (TimeLog $log) => $this->session($log, $employee, $now))->all(),
            // Anything fixed on those days, with the time it replaced.
            'todayFixes' => $this->fixes->byDate($employee, $todays->map(fn (TimeLog $log) => $log->date->toDateString())->unique()->values()->all()),
        ];
    }

    /**
     * @param  array{client: Client, schedule: ?Schedule, date: CarbonImmutable, position: ?string}  $row
     * @param  Collection<int, TimeLog>  $logs
     * @return array<string, mixed>
     */
    private function card(User $employee, array $row, Collection $logs, CarbonImmutable $now, ?Rate $rate = null): array
    {
        $client = $row['client'];
        $schedule = $row['schedule'];
        $clientLogs = $logs->where('client_id', $client->id);
        $open = $clientLogs->first(fn (TimeLog $log) => $log->isOpen());
        $scheduled = null;
        $shiftEnded = false;
        $shiftEnd = null;
        $startOpens = null;

        if ($schedule && $schedule->worksOn($row['date'])) {
            [$start, $end] = $schedule->windowOn($row['date']);
            $shiftEnd = CarbonImmutable::instance($end);
            $shiftEnded = $now->greaterThanOrEqualTo($shiftEnd);
            // The timer opens a few minutes before the shift (System & Rules) and closes when it ends.
            $startOpens = CarbonImmutable::instance($start)->subMinutes($this->rules->integer('timer_early_start_minutes'));
            $scheduled = [
                'hours' => $schedule->expectedHours(),
                'label' => $start->format('g:i A').' – '.$end->format('g:i A'),
            ];
        }

        // After the shift ends the card starts again from zero; the sessions stay in today's summary.
        $completedToday = $clientLogs
            ->filter(fn (TimeLog $log) => ! $log->isOpen() && $log->date->isSameDay($row['date']))
            ->reject(fn (TimeLog $log) => $shiftEnded && $log->time_in->lessThan($shiftEnd))
            ->sum(fn (TimeLog $log) => $log->workedSecondsAt($log->time_out));

        return [
            'id' => $client->id,
            'name' => $client->client_name,
            'position' => $row['position'],
            // Full-Time or Part-Time for this client; older assignments fall back to the contractor's type.
            'employmentType' => $rate?->employment_type?->label() ?? $employee->employment_type?->label(),
            // Set per client (Workforce → Clients); informational only.
            'breakAllowance' => $client->break_allowance_minutes ?? Client::DEFAULT_BREAK_ALLOWANCE,
            'locked' => $this->isLocked($row['date']),
            'scheduled' => $scheduled,
            'status' => match (true) {
                $open !== null => $open->status->value,
                $completedToday > 0 => 'stopped',
                $shiftEnded => 'shift_ended',
                default => 'not_started',
            },
            'shiftEnded' => $shiftEnded,
            'canStart' => $startOpens !== null && $now->greaterThanOrEqualTo($startOpens) && ! $shiftEnded,
            'startHint' => match (true) {
                $startOpens === null => 'No shift today for this client.',
                $shiftEnded => 'Shift ended. Approved extra time goes through an overtime ticket.',
                $now->lessThan($startOpens) => 'You can start the timer from '.$startOpens->format('g:i A').'.',
                default => null,
            },
            'completedSeconds' => $completedToday,
            'timer' => $open ? [
                'id' => $open->id,
                'status' => $open->status->value,
                'startedAt' => $open->time_in->toIso8601String(),
                'breakMinutes' => (int) $open->break_minutes,
                'breakStartedAt' => $open->break_started_at?->toIso8601String(),
                'workedSeconds' => $open->workedSecondsAt($now),
                'longRunning' => $open->workedSecondsAt($now) >= self::LONG_RUNNING_SECONDS,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function session(TimeLog $log, User $employee, CarbonImmutable $now): array
    {
        return [
            'id' => $log->id,
            'date' => $log->date->toDateString(),
            'client' => $log->client?->client_name,
            'start' => $log->time_in->format('g:i A'),
            'end' => $log->time_out?->format('g:i A'),
            'breakUsed' => $log->breakMinutesAt($log->time_out ?? $now),
            'breakAllowance' => $log->client?->break_allowance_minutes ?? Client::DEFAULT_BREAK_ALLOWANCE,
            'workedSeconds' => $log->workedSecondsAt($now),
            'status' => $log->status->value,
            'statusLabel' => $log->status->label(),
            'paidOut' => $this->paidOut($log),
        ];
    }

    private function paidOut(TimeLog $log): bool
    {
        return ! $log->isOpen() && $this->isLocked($log->date);
    }

    private function isLocked(CarbonInterface $date): bool
    {
        return $this->lockedDays[$date->toDateString()] ??= $this->lock->isLocked($date);
    }
}
