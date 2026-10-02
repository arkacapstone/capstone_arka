<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Payroll\ManageOvertime;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\Rate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Overtime. Overtime only counts when a client handler approved it, so the contractor
 * files a ticket naming them; the Super Admin approves it with the amount (Payroll Formula Reference).
 */
class OvertimeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Employee/Overtime', [
            'tickets' => $user->overtimeRequests()
                ->with(['client:id,client_name', 'reviewer:id,name'])
                ->latest('date')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (OvertimeRequest $ticket) => [
                    'id' => $ticket->id,
                    'client' => $ticket->client->client_name,
                    'date' => $ticket->date->toDateString(),
                    'duration' => $ticket->duration(),
                    'clientHandler' => $ticket->client_handler,
                    'reason' => $ticket->reason,
                    'status' => $ticket->status,
                    'statusLabel' => $ticket->statusLabel(),
                    'amount' => $ticket->amount !== null ? (float) $ticket->amount : null,
                    'note' => $ticket->review_note,
                ])->all(),
            'clients' => $user->currentRates()->with('client:id,client_name')->get()
                ->map(fn (Rate $rate) => ['value' => $rate->client_id, 'label' => $rate->client->client_name])->values()->all(),
        ]);
    }

    public function store(Request $request, ManageOvertime $overtime): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'integer', 'min:0', 'max:12'],
            'minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'client_handler' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
        ], attributes: ['client_id' => 'client', 'client_handler' => 'client handler']);

        $minutes = $validated['hours'] * 60 + $validated['minutes'];

        if ($minutes === 0) {
            return back()->withErrors(['hours' => 'Enter how long the overtime was.']);
        }

        $overtime->file($request->user(), [
            'client_id' => $validated['client_id'],
            'date' => $validated['date'],
            'minutes' => $minutes,
            'client_handler' => $validated['client_handler'],
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Overtime ticket sent to the Super Admin.');
    }

    public function cancel(Request $request, OvertimeRequest $overtime, ManageOvertime $manage): RedirectResponse
    {
        abort_unless($overtime->employee_id === $request->user()->id, 404);

        $manage->cancel($overtime);

        return back()->with('success', 'Overtime ticket withdrawn.');
    }
}
