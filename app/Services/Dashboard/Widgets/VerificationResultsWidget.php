<?php

namespace App\Services\Dashboard\Widgets;

use App\Actions\Attendance\ReviewPeriodVerification;
use App\Enums\PayrollPeriodStatus;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceVerification;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Attendance\FixHistory;
use App\Services\Dashboard\Contracts\DashboardWidget;
use App\Services\Payroll\PayrollCalculator;

/**
 * Payroll attendance verification for the most recently opened period.
 *
 * The Admin reviews it: every contractor, every day they fixed with the time it replaced, and the
 * reminder / submit actions. The Super Admin only sees who has submitted (view-only), because the
 * changes were already reviewed by the Admin.
 */
class VerificationResultsWidget implements DashboardWidget
{
    public function __construct(private readonly bool $withChanges = true) {}

    public function key(): string
    {
        return 'verification';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $period = PayrollPeriod::query()->with('adminSubmitter:id,name')->whereNotNull('verification_opened_at')->latest('verification_opened_at')->first();

        if ($period === null) {
            return ['period' => null, 'counts' => null, 'contractors' => []];
        }

        $verifications = AttendanceVerification::query()
            ->when($this->withChanges, fn ($query) => $query->with('corrections.attendance.client:id,client_name'))
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('employee_id');

        $everyone = PayrollCalculator::employeesFor($period)
            ->map(function (User $contractor) use ($verifications, $period) {
                $verification = $verifications->get($contractor->id);
                // Once the Admin submits, anyone who never submitted counts as submitted with their attendance as recorded
                // (also for periods submitted before this was stored).
                $autoSubmitted = (bool) $verification?->auto_submitted || ($period->isSubmittedByAdmin() && $verification?->verified_at === null);

                return [
                    'id' => $contractor->id,
                    'name' => $contractor->name,
                    'code' => $contractor->employee_code,
                    'verifiedAt' => ($verification?->verified_at ?? ($autoSubmitted ? $period->admin_submitted_at : null))?->toIso8601String(),
                    'autoSubmitted' => $autoSubmitted,
                    ...($this->withChanges
                        ? ['fixes' => $verification?->corrections->map(fn (AttendanceCorrection $correction) => FixHistory::present($correction))->all() ?? []]
                        : []),
                ];
            })
            // Verified first (latest on top), then those still to verify by name.
            ->sortBy([fn (array $a, array $b) => ($b['verifiedAt'] ?? '') <=> ($a['verifiedAt'] ?? ''), ['name', 'asc']])
            ->values();

        $inVerification = $period->status === PayrollPeriodStatus::Verification && ! $period->isSubmittedByAdmin();
        $blocker = ReviewPeriodVerification::submitBlocker($period);
        $waiting = $everyone->whereNull('verifiedAt')->count();

        return [
            'period' => [
                'id' => $period->id,
                'name' => $period->period_name,
                'status' => $period->status->value,
                'statusLabel' => $period->status->label(),
                'openedAt' => $period->verification_opened_at->toIso8601String(),
                'fixDeadline' => $period->fixDeadline()->toIso8601String(),
                'fixWindowOpen' => $period->fixWindowOpen(),
                'adminSubmittedAt' => $period->admin_submitted_at?->toIso8601String(),
                'adminSubmittedBy' => $period->adminSubmitter?->name,
                ...($this->withChanges ? [
                    'lastRemindedAt' => $period->last_reminded_at?->toIso8601String(),
                    'lastRemindedCount' => $period->last_reminded_count,
                    'canRemind' => $inVerification && $waiting > 0,
                    'canSubmit' => $blocker === null,
                    'submitBlocker' => $inVerification ? $blocker : null,
                    'submitWarning' => ReviewPeriodVerification::submitWarning($period),
                ] : []),
            ],
            'counts' => [
                'total' => $everyone->count(),
                'verified' => $everyone->count() - $waiting,
                'waiting' => $waiting,
                'autoSubmitted' => $everyone->where('autoSubmitted', true)->count(),
                ...($this->withChanges ? [
                    'fixed' => $everyone->filter(fn (array $row) => $row['fixes'] !== [])->count(),
                    'fixes' => $everyone->sum(fn (array $row) => count($row['fixes'])),
                ] : []),
            ],
            // The Super Admin's view lists only those who already submitted.
            'contractors' => ($this->withChanges ? $everyone : $everyone->whereNotNull('verifiedAt')->values())->all(),
        ];
    }
}
