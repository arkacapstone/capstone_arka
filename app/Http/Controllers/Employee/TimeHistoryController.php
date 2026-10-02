<?php

namespace App\Http\Controllers\Employee;

use App\Enums\TimeLogStatus;
use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Services\Attendance\FixHistory;
use App\Services\TimeTracking\TimerBoard;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Time History (Contractor flow §IV): every past session grouped by day, with what
 * touched that day (a pending correction, or a fix showing the time it replaced).
 */
class TimeHistoryController extends Controller
{
    private const DAYS_PER_PAGE = 10;

    public function __invoke(Request $request, TimerBoard $board, FixHistory $fixHistory): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'client' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(TimeLogStatus::class)],
        ]);

        $user = $request->user();
        $now = CarbonImmutable::now();

        $filtered = fn () => $user->timeLogs()
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('date', '<=', $to))
            ->when($filters['client'] ?? null, fn (Builder $query, int $client) => $query->where('client_id', $client))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        // One page = a few days, each with all of its sessions.
        $allDates = $filtered()->orderByDesc('date')->pluck('date')->map(fn ($date) => $date->toDateString())->unique()->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $days = (new LengthAwarePaginator(
            $allDates->forPage($page, self::DAYS_PER_PAGE)->values(),
            $allDates->count(),
            self::DAYS_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
        $dates = $days->items();

        $sessions = $dates === [] ? collect() : $filtered()
            ->with('client:id,client_name')
            ->whereDate('date', '>=', min($dates))
            ->whereDate('date', '<=', max($dates))
            ->orderByDesc('time_in')
            ->get()
            ->groupBy(fn (TimeLog $log) => $log->date->toDateString());

        $fixes = $fixHistory->byDate($user, $dates);

        $days->through(function (string $date) use ($sessions, $fixes, $board, $user, $now) {
            $daySessions = $sessions->get($date, collect())->map(fn (TimeLog $log) => $board->session($log, $user, $now))->values();

            return [
                'date' => $date,
                'sessions' => $daySessions->all(),
                'workedSeconds' => $daySessions->sum('workedSeconds'),
                'fixes' => $fixes[$date] ?? [],
            ];
        });

        return Inertia::render('Employee/TimeHistory', [
            'days' => Paginated::from($days),
            'filters' => [
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'client' => $filters['client'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'clients' => $user->timeLogs()->with('client:id,client_name')->get()->pluck('client')->filter()->unique('id')
                ->sortBy('client_name')->values()->map(fn ($client) => ['value' => $client->id, 'label' => $client->client_name])->all(),
            'statuses' => TimeLogStatus::options(),
        ]);
    }
}
