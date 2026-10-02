<?php

namespace App\Actions\Payroll;

use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\Rate;
use App\Models\User;
use App\Notifications\OvertimeDecided;
use App\Notifications\OvertimeRequested;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Validation\ValidationException;

/**
 * Overtime tickets (Payroll Formula & Scenario Reference). Overtime only counts when a client handler
 * approved it, so the contractor files a ticket naming them. When the Super Admin approves it, it is paid
 * at the plain hourly rate of that client — Hourly Rate × (Overtime Minutes ÷ 60), no premium — in the
 * next payroll for that client.
 */
class ManageOvertime
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly ActivityLogger $activity,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array{client_id: int, date: string, minutes: int, client_handler: string, reason: string}  $details
     */
    public function file(User $contractor, array $details): OvertimeRequest
    {
        if (! $contractor->currentRates()->where('client_id', $details['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Overtime can only be filed for a client you are assigned to.']);
        }

        $ticket = $contractor->overtimeRequests()->create([...$details, 'status' => OvertimeRequest::STATUS_PENDING]);
        $ticket->load('client:id,client_name');

        $this->activity->log('payroll', 'Filed overtime ticket', $ticket, "{$contractor->name} · {$ticket->client->client_name} · {$ticket->date->toDateString()} · {$ticket->duration()}");
        $this->notifier->superAdmins(new OvertimeRequested($ticket));

        return $ticket;
    }

    /**
     * Overtime Pay = Hourly Rate × (Overtime Minutes ÷ 60), at the rate of that client on the overtime date.
     */
    public static function amountFor(OvertimeRequest $ticket): float
    {
        $rate = Rate::query()
            ->where('employee_id', $ticket->employee_id)
            ->where('client_id', $ticket->client_id)
            ->whereDate('effective_date', '<=', $ticket->date)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $ticket->date))
            ->latest('effective_date')
            ->first()
            ?? throw ValidationException::withMessages(['status' => 'This contractor has no rate for that client on the overtime date, so the overtime cannot be priced.']);

        return round($rate->hourlyRate() * $ticket->minutes / 60, 2);
    }

    public function approve(User $superAdmin, OvertimeRequest $ticket): OvertimeRequest
    {
        $this->ensurePending($ticket);

        $amount = self::amountFor($ticket);

        $ticket->update([
            'status' => OvertimeRequest::STATUS_APPROVED,
            'amount' => $amount,
            'reviewed_by' => $superAdmin->id,
            'reviewed_at' => now(),
        ]);

        $this->payIntoOpenPayroll($ticket);

        return $this->decided($ticket, 'Approved overtime');
    }

    public function reject(User $superAdmin, OvertimeRequest $ticket, ?string $note): OvertimeRequest
    {
        $this->ensurePending($ticket);

        $ticket->update([
            'status' => OvertimeRequest::STATUS_REJECTED,
            'reviewed_by' => $superAdmin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $this->decided($ticket, 'Rejected overtime');
    }

    public function cancel(OvertimeRequest $ticket): OvertimeRequest
    {
        $this->ensurePending($ticket);
        $ticket->update(['status' => OvertimeRequest::STATUS_CANCELLED]);
        $this->activity->log('payroll', 'Withdrew overtime ticket', $ticket);

        return $ticket;
    }

    /**
     * If payroll for that client is already being reviewed, the approved amount is added right away.
     */
    private function payIntoOpenPayroll(OvertimeRequest $ticket): void
    {
        $row = Payroll::query()
            ->with(['period', 'rate'])
            ->where('employee_id', $ticket->employee_id)
            ->where('client_id', $ticket->client_id)
            ->whereHas('period', fn ($query) => $query->whereDate('end_date', '>=', $ticket->date))
            ->get()
            ->filter(fn (Payroll $row) => $row->period->status->allowsAdjustments())
            ->sortBy(fn (Payroll $row) => $row->period->end_date)
            ->first();

        if ($row) {
            $this->calculator->applyOvertime($row, $row->period);
        }
    }

    private function ensurePending(OvertimeRequest $ticket): void
    {
        if (! $ticket->isPending()) {
            throw ValidationException::withMessages(['status' => 'This overtime ticket has already been decided.']);
        }
    }

    private function decided(OvertimeRequest $ticket, string $action): OvertimeRequest
    {
        $ticket->loadMissing('employee', 'client:id,client_name');
        $this->activity->log('payroll', $action, $ticket, "{$ticket->employee->name} · {$ticket->client->client_name} · {$ticket->duration()}");
        $ticket->employee->notify(new OvertimeDecided($ticket));

        return $ticket;
    }
}
