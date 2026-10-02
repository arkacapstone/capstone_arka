<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Leave Request (Contractor flow §VII). The Super Admin decides every request.
 */
class LeaveRequestController extends Controller
{
    public const REASONS = ['Paid holiday', 'Emergency', 'Family matter', 'Personal matter', 'Medical', 'Bereavement', 'Other'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(LeaveRequestStatus::class)],
        ]);

        $requests = $request->user()->leaveRequests()
            ->with('leaveType:id,leave_type_name,is_paid')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('start_date', '<=', $to))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                'id' => $leave->id,
                'startDate' => $leave->start_date->toDateString(),
                'endDate' => $leave->end_date->toDateString(),
                'paid' => (bool) $leave->leaveType?->is_paid,
                'reason' => $leave->reason,
                'clientInformed' => $leave->client_informed,
                'hasProof' => $leave->proof_path !== null,
                'notes' => $leave->notes,
                'status' => $leave->status->value,
                'statusLabel' => $leave->status->label(),
                'canCancel' => $leave->status->isOpen(),
                'submittedAt' => $leave->created_at->toIso8601String(),
                'reviewedAt' => $leave->reviewed_at?->toIso8601String(),
            ]);

        return Inertia::render('Employee/Leave', [
            'requests' => $requests->all(),
            'filters' => [
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'reasons' => self::REASONS,
            'statuses' => LeaveRequestStatus::options(),
        ]);
    }

    public function store(Request $request, SubmitLeaveRequest $submit): RedirectResponse
    {
        $validated = $request->validate([
            'paid' => ['required', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', Rule::in(self::REASONS)],
            'client_informed' => ['required', 'boolean'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'proof.uploaded' => 'This file could not be uploaded. Please use a file under 10 MB.',
            'proof.max' => 'This file is larger than 10 MB. Please use a smaller file.',
        ]);

        $leave = $submit->handle($request->user(), [
            'paid' => (bool) $validated['paid'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'client_informed' => (bool) $validated['client_informed'],
            'notes' => $validated['notes'] ?? null,
        ], $request->file('proof'));

        return back()->with('success', $leave->status === LeaveRequestStatus::NeedsVerification
            ? 'Leave request submitted. Flagged for verification.'
            : 'Leave request submitted.');
    }

    public function cancel(Request $request, LeaveRequest $leave, SubmitLeaveRequest $submit): RedirectResponse
    {
        abort_unless($leave->employee_id === $request->user()->id, 404);

        if (! $leave->status->isOpen()) {
            return back()->with('warning', 'This request has already been decided, so it can no longer be withdrawn.');
        }

        $submit->cancel($leave);

        return back()->with('success', 'Leave request withdrawn.');
    }
}
