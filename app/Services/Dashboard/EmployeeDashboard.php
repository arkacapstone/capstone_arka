<?php

namespace App\Services\Dashboard;

use App\Enums\LeaveRequestStatus;
use App\Models\User;
use App\Services\Attendance\MonthlySummary;
use App\Services\Payslips\EmployeePayslips;
use App\Services\TimeTracking\TimerBoard;
use Carbon\CarbonImmutable;

/**
 * The Contractor Dashboard home (Contractor flow §II). Every card links into its full module,
 * and the greeting's status line is generated from the cards so it never drifts from them.
 */
class EmployeeDashboard
{
    public function __construct(
        private readonly TimerBoard $timers,
        private readonly MonthlySummary $attendance,
        private readonly EmployeePayslips $payslips,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $employee, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();

        $data = [
            'timers' => $this->timers->for($employee, $now),
            'devotional' => [
                'submittedToday' => $employee->devotionals()->whereDate('date', $today)->exists(),
                'thisMonth' => $employee->devotionals()->whereDate('date', '>=', $today->startOfMonth())->whereDate('date', '<=', $today)->count(),
                'daysSoFar' => $today->day,
            ],
            'attendance' => ['month' => $today->format('Y-m'), ...$this->attendance->for($employee, $today)],
            'leave' => $this->pendingLeave($employee),
            'payslip' => $this->payslips->for($employee)->firstWhere('status', 'available'),
        ];

        $data['summary'] = $this->summary($data);

        return $data;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function pendingLeave(User $employee): ?array
    {
        $leave = $employee->leaveRequests()
            ->pending()
            ->with('leaveType:id,is_paid')
            ->orderBy('start_date')
            ->first();

        return $leave ? [
            'id' => $leave->id,
            'startDate' => $leave->start_date->toDateString(),
            'endDate' => $leave->end_date->toDateString(),
            'paid' => (bool) $leave->leaveType?->is_paid,
            'reason' => $leave->reason,
            'status' => $leave->status->value,
            'statusLabel' => $leave->status->label(),
            'submittedAt' => $leave->created_at->toIso8601String(),
            'canWithdraw' => $leave->status->isOpen(),
        ] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function summary(array $data): string
    {
        $running = $data['timers']['running'];
        $parts = [match (true) {
            $running === 0 => 'No timers running.',
            $running === 1 => 'One timer is running.',
            default => "{$running} timers are running.",
        }];

        $missing = $data['devotional']['daysSoFar'] - $data['devotional']['thisMonth'];
        $parts[] = match (true) {
            ! $data['devotional']['submittedToday'] => "Today's devotional is still open until midnight.",
            $missing <= 0 => "You're on a perfect devotional month.",
            default => "Today's devotional is in.",
        };

        if ($data['leave'] && $data['leave']['status'] === LeaveRequestStatus::NeedsVerification->value) {
            $parts[] = 'Your leave request is with the Super Admin for verification.';
        }

        return implode(' ', $parts);
    }
}
