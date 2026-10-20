<?php

namespace App\Actions\Workforce;

use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\Contractor;
use App\Models\Rate;
use App\Models\User;
use App\Notifications\ClientAssigned;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

/**
 * Assigns a contractor to a client at a Super Admin–approved rate (Blueprint §5).
 * A contractor may serve 2–3 clients, each with its own rate.
 */
class AssignClientRate
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly SyncEmploymentType $syncEmploymentType,
    ) {}

    /**
     * @param  array{client_id: int, employment_type?: ?string, gross_pay: numeric, pay_frequency: string, working_days: int, hours_per_day: int, effective_date: string}  $terms
     */
    public function handle(User $employee, array $terms): Rate
    {
        $alreadyAssigned = $employee->currentRates()->forClient($terms['client_id'])->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'client_id' => 'This contractor is already assigned to that client. Change the existing rate instead.',
            ]);
        }

        // Working for this client starts a client assignment (from the rate's effective date); the rate belongs to it.
        Contractor::ensureFor($employee->id);
        $assignment = ClientAssignment::query()->open()->where('contractor_id', $employee->id)->where('client_id', $terms['client_id'])->first()
            ?? ClientAssignment::query()->create([
                'contractor_id' => $employee->id,
                'client_id' => $terms['client_id'],
                'start_date' => $terms['effective_date'],
            ]);

        $client = Client::find($terms['client_id']);
        unset($terms['client_id']);
        $rate = Rate::query()->create([...$terms, 'assignment_id' => $assignment->id]);

        $this->activity->log('workforce', 'Assigned client rate', $rate, "{$employee->name} → {$client->client_name}");
        $employee->notify(new ClientAssigned($rate));
        $this->syncEmploymentType->handle($employee);

        return $rate;
    }
}
