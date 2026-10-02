<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Leave\DecideLeaveRequest;
use App\Actions\Payroll\ManageOvertime;
use App\Actions\Workforce\ManageClientAssignmentRequest;
use App\Enums\EmploymentType;
use App\Enums\LeaveRequestStatus;
use App\Enums\PayFrequency;
use App\Enums\SuperAdminModule;
use App\Http\Controllers\Controller;
use App\Models\ClientAssignmentRequest;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Rate;
use App\Services\Settings\SystemRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Super Admin → Requests & Approvals: leave requests (Blueprint §10), the client assignments Admins
 * give contractors, and contractors' overtime tickets. Only the Super Admin approves and sets money.
 */
class RequestController extends Controller
{
    public function index(Request $request, SystemRules $rules): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:leave,clients,overtime'],
            'tab' => ['nullable', 'in:pending,history'],
        ]);
        $type = $filters['type'] ?? 'leave';
        $tab = $filters['tab'] ?? 'pending';

        return Inertia::render('SuperAdmin/Requests/Index', [
            'module' => ['title' => SuperAdminModule::Requests->label()],
            'type' => $type,
            'tab' => $tab,
            'requests' => $type === 'leave' ? $this->leaveRequests($tab) : [],
            'assignments' => $type === 'clients' ? $this->clientAssignments($tab) : [],
            'overtime' => $type === 'overtime' ? $this->overtimeTickets($tab) : [],
            'pendingCount' => LeaveRequest::query()->pending()->count(),
            'pendingAssignments' => ClientAssignmentRequest::query()->pending()->count(),
            'pendingOvertime' => OvertimeRequest::query()->pending()->count(),
            'rateDefaults' => [
                'workingDays' => $rules->integer('default_working_days'),
                'hoursPerDay' => $rules->integer('default_hours_per_day'),
                'fullTimeHours' => $rules->integer('full_time_hours'),
                'partTimeHours' => $rules->integer('part_time_hours'),
            ],
            // Client assignments are paid per period, never hourly.
            'payFrequencies' => array_values(array_map(
                fn (PayFrequency $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()],
                array_filter(PayFrequency::cases(), fn (PayFrequency $frequency) => $frequency !== PayFrequency::Hourly),
            )),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave, DecideLeaveRequest $decide): RedirectResponse
    {
        $decide->approve($request->user(), $leave);

        return back()->with('success', 'Leave approved. Attendance for those days now shows the leave.');
    }

    public function reject(Request $request, LeaveRequest $leave, DecideLeaveRequest $decide): RedirectResponse
    {
        $decide->reject($request->user(), $leave);

        return back()->with('success', 'Leave request closed. The contractor has been notified.');
    }

    public function proof(LeaveRequest $leave): StreamedResponse
    {
        abort_unless($leave->proof_path && Storage::disk('local')->exists($leave->proof_path), 404);

        return Storage::disk('local')->response($leave->proof_path, null, [], 'inline');
    }

    /**
     * Approving sets the rate (money stays with the Super Admin); the Admin can then schedule the client.
     */
    public function approveAssignment(Request $request, ClientAssignmentRequest $assignment, ManageClientAssignmentRequest $manage, SystemRules $rules): RedirectResponse
    {
        $terms = $request->validate([
            'gross_pay' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'pay_frequency' => ['required', Rule::enum(PayFrequency::class)->except(PayFrequency::Hourly)],
            'effective_date' => ['required', 'date'],
        ]);

        // Working days and hours come from System & Rules: hours follow the Full-Time / Part-Time type the Admin chose.
        $terms['working_days'] = $rules->integer('default_working_days');
        $terms['hours_per_day'] = match ($assignment->employment_type) {
            EmploymentType::FullTime => $rules->integer('full_time_hours'),
            EmploymentType::PartTime => $rules->integer('part_time_hours'),
            default => $rules->integer('default_hours_per_day'),
        };

        $manage->approve($request->user(), $assignment, $terms);

        return back()->with('success', 'Client assignment approved with its rate. The Admin has been notified and can now schedule it.');
    }

    public function rejectAssignment(Request $request, ClientAssignmentRequest $assignment, ManageClientAssignmentRequest $manage): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $manage->reject($request->user(), $assignment, $validated['note'] ?? null);

        return back()->with('success', 'Client assignment rejected. The Admin has been notified.');
    }

    /**
     * Overtime has no rate formula (Payroll Formula Reference), so the Super Admin enters the amount.
     */
    public function approveOvertime(Request $request, OvertimeRequest $overtime, ManageOvertime $manage): RedirectResponse
    {
        $manage->approve($request->user(), $overtime);

        return back()->with('success', 'Overtime approved. It is added to the contractor\'s next payroll for this client.');
    }

    public function rejectOvertime(Request $request, OvertimeRequest $overtime, ManageOvertime $manage): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $manage->reject($request->user(), $overtime, $validated['note'] ?? null);

        return back()->with('success', 'Overtime ticket rejected. The contractor has been notified.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function overtimeTickets(string $tab): array
    {
        return OvertimeRequest::query()
            ->with(['employee:id,name,employee_code', 'employee.currentRates', 'client:id,client_name', 'reviewer:id,name'])
            ->when($tab === 'pending', fn ($query) => $query->pending()->oldest('date'))
            ->when($tab === 'history', fn ($query) => $query->whereIn('status', [OvertimeRequest::STATUS_APPROVED, OvertimeRequest::STATUS_REJECTED])->latest('reviewed_at'))
            ->limit(100)
            ->get()
            ->map(function (OvertimeRequest $ticket) {
                $rate = $ticket->employee->currentRates->firstWhere('client_id', $ticket->client_id);

                return [
                    'id' => $ticket->id,
                    'contractor' => ['name' => $ticket->employee->name, 'code' => $ticket->employee->employee_code],
                    'client' => $ticket->client->client_name,
                    'date' => $ticket->date->toDateString(),
                    'duration' => $ticket->duration(),
                    'minutes' => $ticket->minutes,
                    'clientHandler' => $ticket->client_handler,
                    'reason' => $ticket->reason,
                    // Overtime Pay = Hourly Rate × (minutes ÷ 60), no premium; shown before approving.
                    'hourlyRate' => $rate ? round($rate->hourlyRate(), 2) : null,
                    'overtimePay' => $rate ? round($rate->hourlyRate() * $ticket->minutes / 60, 2) : null,
                    'status' => $ticket->status,
                    'statusLabel' => $ticket->statusLabel(),
                    'amount' => $ticket->amount !== null ? (float) $ticket->amount : null,
                    'reviewer' => $ticket->reviewer?->name,
                    'reviewedAt' => $ticket->reviewed_at?->toIso8601String(),
                    'note' => $ticket->review_note,
                    'submittedAt' => $ticket->created_at->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leaveRequests(string $tab): array
    {
        return LeaveRequest::query()
            ->with(['employee:id,name,employee_code,role', 'leaveType:id,leave_type_name,is_paid', 'reviewer:id,name'])
            ->when($tab === 'pending', fn ($query) => $query->pending()->orderBy('start_date'))
            ->when($tab === 'history', fn ($query) => $query->whereNotIn('status', LeaveRequestStatus::openValues())->orderByDesc('updated_at'))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                'id' => $leave->id,
                'employee' => ['name' => $leave->employee->name, 'code' => $leave->employee->employee_code, 'role' => $leave->employee->role->label()],
                'startDate' => $leave->start_date->toDateString(),
                'endDate' => $leave->end_date->toDateString(),
                'days' => (int) $leave->start_date->diffInDays($leave->end_date) + 1,
                'type' => $leave->leaveType?->leave_type_name,
                'paid' => (bool) $leave->leaveType?->is_paid,
                'reason' => $leave->reason,
                'notes' => $leave->notes,
                'clientInformed' => $leave->client_informed,
                'proofUrl' => $leave->proof_path ? route('super-admin.requests.leave.proof', $leave) : null,
                'status' => $leave->status->value,
                'statusLabel' => $leave->status->label(),
                'reviewer' => $leave->reviewer?->name,
                'reviewedAt' => $leave->reviewed_at?->toIso8601String(),
                'submittedAt' => $leave->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clientAssignments(string $tab): array
    {
        return ClientAssignmentRequest::query()
            ->with(['employee:id,name,employee_code,employment_type', 'employee.currentRates.client:id,client_name', 'client:id,client_name,client_code', 'requester:id,name', 'reviewer:id,name'])
            ->when($tab === 'pending', fn ($query) => $query->pending()->oldest())
            ->when($tab === 'history', fn ($query) => $query->whereIn('status', [ClientAssignmentRequest::STATUS_APPROVED, ClientAssignmentRequest::STATUS_REJECTED])->latest('reviewed_at'))
            ->limit(100)
            ->get()
            ->map(fn (ClientAssignmentRequest $assignment) => [
                'id' => $assignment->id,
                'contractor' => [
                    'name' => $assignment->employee->name,
                    'code' => $assignment->employee->employee_code,
                    'type' => $assignment->employee->employment_type?->label(),
                    'currentClients' => $assignment->employee->currentRates->map(fn (Rate $rate) => $rate->client?->client_name)->filter()->values()->all(),
                ],
                'client' => ['name' => $assignment->clientName(), 'code' => $assignment->client?->client_code, 'isNew' => $assignment->client_id === null],
                'employmentType' => $assignment->employment_type?->value,
                'employmentTypeLabel' => $assignment->employment_type?->label(),
                'startDate' => $assignment->start_date?->toDateString(),
                'status' => $assignment->status,
                'statusLabel' => $assignment->statusLabel(),
                'requestedBy' => $assignment->requester->name,
                'submittedAt' => $assignment->created_at->toIso8601String(),
                'reviewer' => $assignment->reviewer?->name,
                'reviewedAt' => $assignment->reviewed_at?->toIso8601String(),
                'note' => $assignment->review_note,
            ])
            ->all();
    }
}
