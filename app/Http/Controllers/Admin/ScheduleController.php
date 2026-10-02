<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Scheduling\SaveSchedule;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Settings\SystemRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Scheduling → Schedules (Blueprint §6, Admin flow §IV). Schedules are for clients the
 * Super Admin already approved for the contractor (given on the Clients tab).
 */
class ScheduleController extends Controller
{
    public function index(Request $request, SystemRules $rules): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'client' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:current,active,inactive,ended'],
            'day' => ['nullable', Rule::enum(Weekday::class)],
        ]);
        $today = CarbonImmutable::today();
        $status = $filters['status'] ?? 'current';

        $schedules = Schedule::query()
            ->with(['employee:id,name,employee_code', 'client:id,client_name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereHas('employee', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%"))
            ))
            ->when($filters['client'] ?? null, fn (Builder $query, int $client) => $query->where('client_id', $client))
            ->when($filters['day'] ?? null, fn (Builder $query, string $day) => $query->where('working_days', 'like', "%\"{$day}\"%"))
            ->when($status === 'ended', fn (Builder $query) => $query->whereDate('end_date', '<', $today))
            ->when($status !== 'ended', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today)
            ))
            ->when(in_array($status, ['active', 'inactive'], true), fn (Builder $query) => $query->where('status', $status))
            ->join('users', 'users.id', '=', 'schedules.employee_id')
            ->orderBy('users.name')
            ->orderBy('schedules.start_time')
            ->orderBy('schedules.id')
            ->select('schedules.*')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Scheduling/Index', [
            'schedules' => ScheduleResource::collection($schedules),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'client' => $filters['client'] ?? '',
                'status' => $status,
                'day' => $filters['day'] ?? '',
            ],
            'employees' => $this->employeeOptions(),
            'clients' => Client::query()->active()->orderBy('client_name')->get(['id', 'client_name'])
                ->map(fn (Client $client) => ['value' => $client->id, 'label' => $client->client_name])->all(),
            'weekdays' => Weekday::options(),
            'preselectEmployee' => $request->integer('employee') ?: null,
            'hours' => ['full_time' => $rules->integer('full_time_hours'), 'part_time' => $rules->integer('part_time_hours')],
            'preselectClient' => $request->integer('employee') ? ($request->integer('client') ?: null) : null,
        ]);
    }

    public function store(ScheduleRequest $request, SaveSchedule $saveSchedule): RedirectResponse
    {
        $result = $saveSchedule->create($request->validated());

        return $this->withOverlapWarning(back()->with('success', 'Schedule saved.'), $result['overlaps']);
    }

    public function update(ScheduleRequest $request, Schedule $schedule, SaveSchedule $saveSchedule): RedirectResponse
    {
        $result = $saveSchedule->change($schedule, $request->validated());

        $message = $result['schedule']->is($schedule)
            ? 'Schedule updated.'
            : 'Schedule changed. The previous schedule is kept in history.';

        return $this->withOverlapWarning(back()->with('success', $message), $result['overlaps']);
    }

    public function updateStatus(Request $request, Schedule $schedule, SaveSchedule $saveSchedule): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

        $saveSchedule->setStatus($schedule, $validated['status']);

        return back()->with('success', $validated['status'] === 'active' ? 'Schedule activated.' : 'Schedule deactivated.');
    }

    /**
     * Active contractors and the clients already approved for them.
     *
     * @return list<array{value: int, label: string, clients: list<int>, clientTypes: array<int, ?string>, employmentType: ?string}>
     */
    private function employeeOptions(): array
    {
        return User::query()
            ->workforce()
            ->active()
            ->with('currentRates:id,employee_id,client_id,employment_type')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'employment_type'])
            ->map(fn (User $employee) => [
                'value' => $employee->id,
                'label' => "{$employee->name} ({$employee->employee_code})",
                'clients' => $employee->currentRates->pluck('client_id')->all(),
                // Full-Time / Part-Time per client decides the schedule length.
                'clientTypes' => $employee->currentRates->mapWithKeys(fn ($rate) => [$rate->client_id => $rate->employment_type?->value])->all(),
                'employmentType' => $employee->employment_type?->label(),
            ])
            ->all();
    }

    /**
     * Overlaps are allowed (e.g. two part-time clients), so this is a quiet heads-up, not an error.
     *
     * @param  Collection<int, Schedule>  $overlaps
     */
    private function withOverlapWarning(RedirectResponse $response, Collection $overlaps): RedirectResponse
    {
        if ($overlaps->isEmpty()) {
            return $response;
        }

        $list = $overlaps->map(fn (Schedule $schedule) => $schedule->client->client_name.' '.substr($schedule->start_time, 0, 5).'–'.substr($schedule->end_time, 0, 5))->implode(', ');

        return $response->with('warning', "Saved. Heads-up: this overlaps another active schedule ({$list}). Keep it if that's intentional.");
    }
}
