<?php

namespace App\Actions\Leave;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\LeaveRequestDecided;
use App\Services\ActivityLogger;
use App\Services\Attendance\AttendanceLock;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Super Admin's decision on a leave request (Blueprint §10, Requests & Approvals).
 * An approved request marks each scheduled working day as paid or unpaid leave on attendance.
 */
class DecideLeaveRequest
{
    public function __construct(
        private readonly AttendanceLock $lock,
        private readonly ActivityLogger $activity,
    ) {}

    public function approve(User $superAdmin, LeaveRequest $leave): LeaveRequest
    {
        $this->ensureOpen($leave);

        DB::transaction(function () use ($superAdmin, $leave) {
            $leave->update(['status' => LeaveRequestStatus::Approved, 'reviewed_by' => $superAdmin->id, 'reviewed_at' => now()]);
            $this->markAttendance($leave);
        });

        return $this->finish($leave, 'Approved leave request');
    }

    public function reject(User $superAdmin, LeaveRequest $leave): LeaveRequest
    {
        $this->ensureOpen($leave);

        $leave->update(['status' => LeaveRequestStatus::Rejected, 'reviewed_by' => $superAdmin->id, 'reviewed_at' => now()]);

        return $this->finish($leave, 'Rejected leave request');
    }

    /**
     * Every scheduled working day in the range gets a leave attendance record, one per client.
     * Days that already have clocked time or are locked for payroll are left as they are.
     */
    private function markAttendance(LeaveRequest $leave): void
    {
        $leave->loadMissing('leaveType');
        $status = $leave->leaveType->is_paid ? AttendanceStatus::PaidLeave : AttendanceStatus::UnpaidLeave;

        foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $day) {
            $date = CarbonImmutable::parse($day->toDateString());

            if ($this->lock->isLocked($date)) {
                continue;
            }

            Schedule::query()
                ->where('employee_id', $leave->employee_id)
                ->active()
                ->applicableOn($date)
                ->get()
                ->filter(fn (Schedule $schedule) => $schedule->worksOn($date))
                ->each(function (Schedule $schedule) use ($leave, $date, $status) {
                    $attendance = Attendance::query()
                        ->where('employee_id', $leave->employee_id)
                        ->where('client_id', $schedule->client_id)
                        ->whereDate('date', $date)
                        ->first() ?? new Attendance(['employee_id' => $leave->employee_id, 'client_id' => $schedule->client_id, 'date' => $date]);

                    if ($attendance->time_in !== null) {
                        return;
                    }

                    $attendance->fill([
                        'schedule_id' => $schedule->id,
                        'status' => $status,
                        'leave_type_id' => $leave->leave_type_id,
                        'actual_hours' => null,
                        'late_minutes' => 0,
                        'undertime_minutes' => 0,
                        'break_minutes' => 0,
                    ])->save();
                });
        }
    }

    private function ensureOpen(LeaveRequest $leave): void
    {
        if (! $leave->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'This leave request has already been decided.']);
        }
    }

    private function finish(LeaveRequest $leave, string $action): LeaveRequest
    {
        $leave->loadMissing('employee');
        $this->activity->log('requests', $action, $leave, "{$leave->employee->name} · {$leave->start_date->format('M j')} – {$leave->end_date->format('M j, Y')}");
        $leave->employee->notify(new LeaveRequestDecided($leave));

        return $leave;
    }
}
