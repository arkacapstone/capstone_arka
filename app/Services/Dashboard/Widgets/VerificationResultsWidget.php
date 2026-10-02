<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceVerification;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Attendance\FixHistory;
use App\Services\Dashboard\Contracts\DashboardWidget;
use App\Services\Payroll\PayrollCalculator;

/**
 * Payroll attendance verification results for the most recently opened period: which contractors
 * submitted their attendance as verified, and every day they fixed with the time it replaced.
 */
class VerificationResultsWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'verification';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $period = PayrollPeriod::query()->whereNotNull('verification_opened_at')->latest('verification_opened_at')->first();

        if ($period === null) {
            return ['period' => null, 'counts' => null, 'contractors' => []];
        }

        $verifications = AttendanceVerification::query()
            ->with('corrections.attendance.client:id,client_name')
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('employee_id');

        $contractors = PayrollCalculator::employeesFor($period)
            ->map(function (User $contractor) use ($verifications) {
                $verification = $verifications->get($contractor->id);

                return [
                    'id' => $contractor->id,
                    'name' => $contractor->name,
                    'code' => $contractor->employee_code,
                    'verifiedAt' => $verification?->verified_at?->toIso8601String(),
                    'fixes' => $verification?->corrections->map(fn (AttendanceCorrection $correction) => FixHistory::present($correction))->all() ?? [],
                ];
            })
            // Verified first (latest on top), then those still to verify by name.
            ->sortBy([fn (array $a, array $b) => ($b['verifiedAt'] ?? '') <=> ($a['verifiedAt'] ?? ''), ['name', 'asc']])
            ->values();

        return [
            'period' => [
                'id' => $period->id,
                'name' => $period->period_name,
                'status' => $period->status->value,
                'statusLabel' => $period->status->label(),
                'openedAt' => $period->verification_opened_at->toIso8601String(),
                'fixDeadline' => $period->fixDeadline()->toIso8601String(),
                'fixWindowOpen' => $period->fixWindowOpen(),
            ],
            'counts' => [
                'total' => $contractors->count(),
                'verified' => $contractors->whereNotNull('verifiedAt')->count(),
                'fixed' => $contractors->filter(fn (array $row) => $row['fixes'] !== [])->count(),
                'fixes' => $contractors->sum(fn (array $row) => count($row['fixes'])),
            ],
            'contractors' => $contractors->all(),
        ];
    }
}
