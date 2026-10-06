<?php

namespace App\Actions\Workforce;

use App\Models\Client;
use App\Models\ClientAssignmentRequest;
use App\Models\User;
use App\Notifications\ClientAssignmentDecided;
use App\Notifications\ClientAssignmentRequested;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Client assignments: only Admins add clients, typing the client's name and whether the work is
 * Full-Time or Part-Time. The Super Admin only approves (setting the rate, since rates are money) or
 * rejects. A client typed for the first time is created when approved; a known name is reused.
 */
class ManageClientAssignmentRequest
{
    public function __construct(
        private readonly AssignClientRate $assignClientRate,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array{employee_id: int, client_name: string, employment_type: string, start_date: string}  $details
     */
    public function submit(User $admin, array $details): ClientAssignmentRequest
    {
        $contractor = User::findOrFail($details['employee_id']);
        $name = trim(preg_replace('/\s+/', ' ', $details['client_name']));
        $client = Client::named($name);

        if ($client && $contractor->currentRates()->where('client_id', $client->id)->exists()) {
            throw ValidationException::withMessages(['client_name' => "{$contractor->name} already works for {$client->client_name}."]);
        }

        if ($this->waiting($contractor, $client, $name)) {
            throw ValidationException::withMessages(['client_name' => 'This client assignment is already waiting for the Super Admin.']);
        }

        $request = ClientAssignmentRequest::create([
            'employee_id' => $contractor->id,
            'client_id' => $client?->id,
            'client_name' => $client?->client_name ?? $name,
            'employment_type' => $details['employment_type'],
            'break_allowance_minutes' => $details['break_allowance_minutes'] ?? Client::DEFAULT_BREAK_ALLOWANCE,
            'start_date' => $details['start_date'],
            'requested_by' => $admin->id,
            'status' => ClientAssignmentRequest::STATUS_PENDING,
        ]);

        $this->activity->log('scheduling', 'Requested client assignment', $request, "{$contractor->name} → {$request->clientName()} ({$request->employment_type->label()})");
        $this->notifier->superAdmins(new ClientAssignmentRequested($request));

        return $request;
    }

    /**
     * @param  array{gross_pay: numeric, pay_frequency: string, working_days: int, hours_per_day: int, effective_date: string}  $terms
     */
    public function approve(User $superAdmin, ClientAssignmentRequest $request, array $terms): ClientAssignmentRequest
    {
        $this->ensurePending($request);
        $request->loadMissing('employee', 'client');

        DB::transaction(function () use ($superAdmin, $request, $terms) {
            $client = $this->resolveClient($request);
            // The break allowance follows the client: it applies to everyone on it, Full-Time or Part-Time.
            $client->update(['break_allowance_minutes' => $request->break_allowance_minutes]);

            $rate = $this->assignClientRate->handle($request->employee, [
                ...$terms,
                'client_id' => $client->id,
                'employment_type' => $request->employment_type?->value,
            ]);

            $request->update([
                'client_id' => $client->id,
                'status' => ClientAssignmentRequest::STATUS_APPROVED,
                'reviewed_by' => $superAdmin->id,
                'reviewed_at' => now(),
                'rate_id' => $rate->id,
            ]);
        });

        return $this->decided($request->refresh(), 'Approved client assignment');
    }

    public function reject(User $superAdmin, ClientAssignmentRequest $request, ?string $note): ClientAssignmentRequest
    {
        $this->ensurePending($request);

        $request->update([
            'status' => ClientAssignmentRequest::STATUS_REJECTED,
            'reviewed_by' => $superAdmin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $this->decided($request, 'Rejected client assignment');
    }

    /**
     * The Admin can withdraw it while it is still waiting.
     */
    public function cancel(ClientAssignmentRequest $request): ClientAssignmentRequest
    {
        $this->ensurePending($request);

        $request->update(['status' => ClientAssignmentRequest::STATUS_CANCELLED]);
        $request->loadMissing('employee:id,name', 'client:id,client_name');
        $this->activity->log('scheduling', 'Withdrew client assignment request', $request, "{$request->employee->name} → {$request->clientName()}");

        return $request;
    }

    /**
     * The client the Admin named: the known one, or a new one created now that it is approved.
     */
    private function resolveClient(ClientAssignmentRequest $request): Client
    {
        $client = $request->client ?? Client::named((string) $request->client_name);

        if ($client === null) {
            $client = Client::create(['client_name' => $request->client_name, 'client_code' => Client::nextCode(), 'is_active' => true]);
            $this->activity->log('workforce', 'Created client', $client, "{$client->client_name} ({$client->client_code})");
        } elseif (! $client->is_active) {
            $client->update(['is_active' => true]);
        }

        return $client;
    }

    private function waiting(User $contractor, ?Client $client, string $name): bool
    {
        return ClientAssignmentRequest::query()->pending()
            ->where('employee_id', $contractor->id)
            ->where(fn ($query) => $client
                ? $query->where('client_id', $client->id)->orWhereRaw('LOWER(client_name) = ?', [mb_strtolower($name)])
                : $query->whereRaw('LOWER(client_name) = ?', [mb_strtolower($name)]))
            ->exists();
    }

    private function ensurePending(ClientAssignmentRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'This client assignment has already been decided.']);
        }
    }

    private function decided(ClientAssignmentRequest $request, string $action): ClientAssignmentRequest
    {
        $request->loadMissing('employee:id,name', 'client:id,client_name', 'requester');
        $this->activity->log('requests', $action, $request, "{$request->employee->name} → {$request->clientName()}");
        $request->requester->notify(new ClientAssignmentDecided($request));

        return $request;
    }
}
