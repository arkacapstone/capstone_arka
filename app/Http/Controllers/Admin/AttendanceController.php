<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\CorrectAttendance;
use App\Actions\Attendance\FieldCorrected;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Services\Attendance\AttendanceLock;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Attendance Management & Corrections (Blueprint §7–§8, Admin flow §V).
 */
class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceLock $lock): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'employee' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'tab' => ['nullable', 'in:records,log'],
            'fix' => ['nullable', 'integer'],
        ]);

        $today = CarbonImmutable::today();
        $from = isset($filters['from']) ? CarbonImmutable::parse($filters['from']) : $today;
        $to = isset($filters['to']) ? CarbonImmutable::parse($filters['to']) : $today;

        $range = Attendance::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($filters['employee'] ?? null, fn (Builder $query, int $id) => $query->where('employee_id', $id));

        $counts = (clone $range)->selectRaw('status, count(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');
        $count = fn (AttendanceStatus ...$statuses) => (int) collect($statuses)->sum(fn ($status) => $counts[$status->value] ?? 0);

        $records = (clone $range)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['employee:id,name,employee_code', 'client:id,client_name', 'schedule', 'leaveType:id,leave_type_name'])
            ->withCount('corrections')
            ->orderByDesc('date')
            ->orderBy('employee_id')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Attendance $attendance) => $this->present($attendance, $lock));

        $fix = isset($filters['fix']) ? Attendance::with(['employee:id,name,employee_code', 'client:id,client_name', 'schedule'])->find($filters['fix']) : null;

        return Inertia::render('Admin/Attendance/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'employee' => $filters['employee'] ?? '',
                'status' => $filters['status'] ?? '',
                'tab' => $filters['tab'] ?? 'records',
            ],
            'summary' => [
                'present' => $count(AttendanceStatus::Present, AttendanceStatus::Undertime),
                'late' => $count(AttendanceStatus::Late),
                'absent' => $count(AttendanceStatus::Absent),
                'onLeave' => $count(AttendanceStatus::PaidLeave, AttendanceStatus::UnpaidLeave),
                'incomplete' => $count(AttendanceStatus::Incomplete),
            ],
            'records' => Paginated::from($records),
            'log' => AttendanceCorrection::query()
                ->where('status', '!=', CorrectionStatus::Pending)
                ->with(['employee:id,name', 'reviewer:id,name'])
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (AttendanceCorrection $correction) => $this->presentCorrection($correction))
                ->all(),
            'fix' => $fix ? $this->present($fix, $lock) : null,
            // Admins never correct or review their own attendance.
            'employees' => User::query()->workforce()->whereKeyNot($request->user()->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])->all(),
            'statuses' => AttendanceStatus::options(),
        ]);
    }

    public function correct(Request $request, CorrectAttendance $correctAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'attendance_id' => ['nullable', 'integer', Rule::exists(Attendance::class, 'id')],
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues()), Rule::notIn([$request->user()->id])],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'time_in' => ['nullable', 'required_without:time_out', 'date_format:H:i'],
            'time_out' => ['nullable', 'required_without:time_in', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $attendance = isset($validated['attendance_id']) ? Attendance::find($validated['attendance_id']) : null;
        abort_if($attendance && $attendance->employee_id !== (int) $validated['employee_id'], 422);

        $correctAttendance->handle(
            $request->user(),
            User::findOrFail($validated['employee_id']),
            CarbonImmutable::parse($attendance?->date->toDateString() ?? $validated['date']),
            $attendance,
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null,
            $validated['reason'],
        );

        return back()->with('success', 'Attendance corrected. The original value is kept in the correction log.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Attendance $attendance, AttendanceLock $lock): array
    {
        $window = $attendance->schedule?->windowOn($attendance->date);
        $isLeave = $attendance->status->isLeave();

        return [
            'id' => $attendance->id,
            'date' => $attendance->date->toDateString(),
            'employee' => ['id' => $attendance->employee->id, 'name' => $attendance->employee->name, 'code' => $attendance->employee->employee_code],
            'client' => $attendance->client?->client_name,
            'scheduled' => $window ? $window[0]->format('g:i A').' – '.$window[1]->format('g:i A') : null,
            'timeIn' => $attendance->time_in?->format('H:i'),
            'timeOut' => $attendance->time_out?->format('H:i'),
            'timeInLabel' => $attendance->time_in?->format('g:i A'),
            'timeOutLabel' => $attendance->time_out?->format('g:i A'),
            'hours' => $attendance->actual_hours !== null ? (float) $attendance->actual_hours : null,
            'lateMinutes' => $attendance->late_minutes,
            'undertimeMinutes' => $attendance->undertime_minutes,
            'status' => $attendance->status->value,
            'statusLabel' => $isLeave && $attendance->leaveType
                ? "{$attendance->status->label()} · {$attendance->leaveType->leave_type_name}"
                : $attendance->status->label(),
            'corrections' => $attendance->corrections_count ?? 0,
            'locked' => $lock->isLocked($attendance->date),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCorrection(AttendanceCorrection $correction): array
    {
        $pair = fn (?string $in, ?string $out) => collect([$in, $out])->map(fn ($time) => $time ? substr($time, 0, 5) : '—')->implode(' – ');

        return [
            'id' => $correction->id,
            'date' => $correction->date->toDateString(),
            'employee' => $correction->employee->name,
            'source' => $correction->source,
            'field' => FieldCorrected::label($correction->field_corrected),
            'original' => $pair($correction->original_time_in, $correction->original_time_out),
            'requested' => $pair($correction->requested_time_in, $correction->requested_time_out),
            'reason' => $correction->reason,
            'status' => $correction->status->value,
            'remarks' => $correction->admin_remarks,
            'reviewer' => $correction->reviewer?->name,
            'reviewedAt' => $correction->reviewed_at?->toIso8601String(),
        ];
    }
}
