<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Workforce\ManageClientAssignmentRequest;
use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientAssignmentRequest;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Settings\SystemRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Scheduling → Clients: only Admins add clients. The Admin types the client's name (a known
 * client is reused) and whether the work is Full-Time or Part-Time;
 * the Super Admin approves it and sets the rate. Then the Admin schedules it on the Schedules tab.
 */
class ClientAssignmentController extends Controller
{
    public function index(Request $request, SystemRules $rules): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'client' => ['nullable', 'integer'],
        ]);

        // Active, not-ended schedules per contractor and client.
        $scheduled = Schedule::query()
            ->active()
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->get(['employee_id', 'client_id'])
            ->countBy(fn (Schedule $schedule) => "{$schedule->employee_id}-{$schedule->client_id}");

        $assignments = Rate::query()
            ->current()
            ->with(['employee:id,name,employee_code,employment_type', 'client:id,client_name,client_code'])
            ->whereHas('employee', fn (Builder $query) => $query
                ->whereIn('role', UserRole::workforceValues())
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                    fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%")
                )))
            ->when($filters['client'] ?? null, fn (Builder $query, int $client) => $query->where('client_id', $client))
            ->join('users', 'users.id', '=', 'rates.employee_id')
            ->orderBy('users.name')
            ->select('rates.*')
            ->get()
            ->map(fn (Rate $rate) => [
                'id' => $rate->id,
                'contractor' => ['id' => $rate->employee->id, 'name' => $rate->employee->name, 'code' => $rate->employee->employee_code, 'type' => $rate->employee->employment_type?->label()],
                'client' => ['id' => $rate->client->id, 'name' => $rate->client->client_name, 'code' => $rate->client->client_code],
                'type' => $rate->employment_type?->label(),
                'since' => $rate->effective_date->toDateString(),
                'schedules' => $scheduled->get("{$rate->employee_id}-{$rate->client_id}", 0),
            ]);

        return Inertia::render('Admin/Scheduling/Clients', [
            'assignments' => $assignments->all(),
            'requests' => $this->requests(),
            'filters' => ['search' => $filters['search'] ?? '', 'client' => $filters['client'] ?? ''],
            'contractors' => User::query()->workforce()->active()->orderBy('name')->get(['id', 'name', 'employee_code'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => "{$user->name} ({$user->employee_code})"])->all(),
            'clients' => Client::query()->active()->orderBy('client_name')->get(['id', 'client_name'])
                ->map(fn (Client $client) => ['value' => $client->id, 'label' => $client->client_name])->all(),
            // Suggestions while typing: every client Admins have added before.
            'clientNames' => Client::query()->orderBy('client_name')->pluck('client_name')->unique()->values()->all(),
            'employmentTypes' => EmploymentType::options(),
            'hours' => ['full_time' => $rules->integer('full_time_hours'), 'part_time' => $rules->integer('part_time_hours')],
            // What each contractor already has or is waiting for (lower-case names), to warn before sending.
            'taken' => $this->takenClients(),
        ]);
    }

    public function store(Request $request, ManageClientAssignmentRequest $manage): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues())->where('status', 'active')],
            'client_name' => ['required', 'string', 'min:2', 'max:100'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ], ['start_date.after_or_equal' => 'The start date cannot be in the past.'], attributes: ['employee_id' => 'contractor', 'client_name' => 'client name', 'employment_type' => 'Full-Time / Part-Time', 'start_date' => 'start date']);

        $manage->submit($request->user(), $validated);

        return back()->with('success', 'Sent to the Super Admin. Once approved, schedule it on the Schedules tab.');
    }

    public function cancel(ClientAssignmentRequest $assignmentRequest, ManageClientAssignmentRequest $manage): RedirectResponse
    {
        $manage->cancel($assignmentRequest);

        return back()->with('success', 'Client assignment request withdrawn.');
    }

    /**
     * Requests still waiting, plus those decided in the last two weeks so Admins see the outcome.
     *
     * @return list<array<string, mixed>>
     */
    private function requests(): array
    {
        return ClientAssignmentRequest::query()
            ->with(['employee:id,name,employee_code', 'client:id,client_name', 'requester:id,name', 'reviewer:id,name'])
            ->where(fn (Builder $query) => $query
                ->where('status', ClientAssignmentRequest::STATUS_PENDING)
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('status', [ClientAssignmentRequest::STATUS_APPROVED, ClientAssignmentRequest::STATUS_REJECTED])
                    ->where('reviewed_at', '>=', now()->subDays(14))))
            ->orderByRaw('status = ? desc', [ClientAssignmentRequest::STATUS_PENDING])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ClientAssignmentRequest $assignment) => [
                'id' => $assignment->id,
                'contractor' => ['name' => $assignment->employee->name, 'code' => $assignment->employee->employee_code],
                'client' => $assignment->clientName(),
                'isNewClient' => $assignment->client_id === null,
                'type' => $assignment->employment_type?->label(),
                'startDate' => $assignment->start_date?->toDateString(),
                'status' => $assignment->status,
                'statusLabel' => $assignment->statusLabel(),
                'requestedBy' => $assignment->requester->name,
                'reviewer' => $assignment->reviewer?->name,
                'note' => $assignment->review_note,
                'submittedAt' => $assignment->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, list<string>> contractor id → lower-case client names already assigned or waiting
     */
    private function takenClients(): array
    {
        $taken = [];

        foreach (Rate::query()->current()->with('client:id,client_name')->get(['employee_id', 'client_id']) as $rate) {
            $taken[$rate->employee_id][] = mb_strtolower($rate->client->client_name);
        }

        foreach (ClientAssignmentRequest::query()->pending()->with('client:id,client_name')->get(['employee_id', 'client_id', 'client_name']) as $request) {
            $taken[$request->employee_id][] = mb_strtolower($request->clientName());
        }

        return $taken;
    }
}
