<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Attendance\VerifyAttendance;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceVerification;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Attendance\AttendanceLock;
use App\Services\Attendance\FixHistory;
use App\Services\Attendance\MonthlySummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Attendance (Contractor flow §VI). There are no separate correction requests: when the
 * Super Admin opens payroll verification, the contractor gets a one-time chance to fix their days.
 */
class AttendanceController extends Controller
{
    public function index(Request $request, MonthlySummary $summary, AttendanceLock $lock): Response
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'tab' => ['nullable', Rule::in(['history', 'verification'])],
        ]);

        $user = $request->user();
        $month = isset($filters['month']) ? CarbonImmutable::createFromFormat('Y-m-d', "{$filters['month']}-01")->startOfDay() : CarbonImmutable::today()->startOfMonth();

        $records = $user->attendances()
            ->with(['client:id,client_name', 'schedule', 'leaveType:id,leave_type_name'])
            ->whereDate('date', '>=', $month->startOfMonth())
            ->whereDate('date', '<=', $month->endOfMonth())
            ->orderByDesc('date')
            ->orderBy('client_id')
            ->get()
            ->map(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'date' => $attendance->date->toDateString(),
                'client' => $attendance->client?->client_name,
                'timeIn' => $attendance->time_in?->format('H:i'),
                'timeOut' => $attendance->time_out?->format('H:i'),
                'timeInLabel' => $attendance->time_in?->format('g:i A'),
                'timeOutLabel' => $attendance->time_out?->format('g:i A'),
                'hours' => $attendance->actual_hours !== null ? (float) $attendance->actual_hours : null,
                'status' => $attendance->status->value,
                'statusLabel' => $attendance->status->label(),
                'locked' => $lock->isLocked($attendance->date),
            ]);

        return Inertia::render('Employee/Attendance', [
            'month' => $month->format('Y-m'),
            'summary' => $summary->for($user, $month),
            'records' => $records->all(),
            'tab' => $filters['tab'] ?? 'history',
            'verification' => $this->verification($user),
        ]);
    }

    public function verificationFix(Request $request, PayrollPeriod $period, VerifyAttendance $verify): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'attendance_id' => ['required', 'integer', Rule::exists(Attendance::class, 'id')->where('employee_id', $user->id)],
            'time_in' => ['nullable', 'required_without:time_out', 'date_format:H:i'],
            'time_out' => ['nullable', 'required_without:time_in', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $verify->fix($user, $period, Attendance::findOrFail($validated['attendance_id']), $validated['time_in'] ?? null, $validated['time_out'] ?? null, $validated['reason']);

        return back()->with('success', 'Attendance fixed. You can fix more days until midnight; when everything looks right, submit your attendance as verified.');
    }

    public function verificationSubmit(Request $request, PayrollPeriod $period, VerifyAttendance $verify): RedirectResponse
    {
        $verify->submit($request->user(), $period);

        return back()->with('success', 'Attendance submitted as verified. Thank you!');
    }

    /**
     * The payroll period the contractor is asked to verify, with every day in it.
     *
     * @return array<string, mixed>|null
     */
    private function verification(User $user): ?array
    {
        $period = VerifyAttendance::openPeriodFor($user);

        if ($period === null) {
            return null;
        }

        $verification = AttendanceVerification::query()
            ->with('corrections.attendance.client:id,client_name')
            ->where('period_id', $period->id)
            ->where('employee_id', $user->id)
            ->first();

        return [
            'periodId' => $period->id,
            'name' => $period->period_name,
            'cutoffDate' => $period->cutoff_date->toDateString(),
            'verifiedAt' => $verification?->verified_at?->toIso8601String(),
            'canFix' => $period->fixWindowOpen() && ! $period->isSubmittedByAdmin(),
            // Once the Admin submits the verified period to the Super Admin, nothing more can be changed.
            'closed' => $period->isSubmittedByAdmin(),
            // Never submitted, so it was submitted for them with their attendance as recorded.
            'autoSubmitted' => (bool) $verification?->auto_submitted || ($period->isSubmittedByAdmin() && $verification?->verified_at === null),
            'fixDeadline' => $period->fixDeadline()?->toIso8601String(),
            'fixes' => $verification?->corrections->map(fn (AttendanceCorrection $correction) => FixHistory::present($correction))->all() ?? [],
            'records' => $user->attendances()
                ->with('client:id,client_name')
                ->whereDate('date', '>=', $period->start_date)
                ->whereDate('date', '<=', $period->end_date)
                ->orderBy('date')
                ->orderBy('client_id')
                ->get()
                ->map(fn (Attendance $attendance) => [
                    'id' => $attendance->id,
                    'date' => $attendance->date->toDateString(),
                    'client' => $attendance->client?->client_name,
                    'timeIn' => $attendance->time_in?->format('H:i'),
                    'timeOut' => $attendance->time_out?->format('H:i'),
                    'timeInLabel' => $attendance->time_in?->format('g:i A'),
                    'timeOutLabel' => $attendance->time_out?->format('g:i A'),
                    'hours' => $attendance->actual_hours !== null ? (float) $attendance->actual_hours : null,
                    'status' => $attendance->status->value,
                    'statusLabel' => $attendance->status->label(),
                ])
                ->all(),
        ];
    }
}
