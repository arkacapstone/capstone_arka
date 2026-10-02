<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\Dashboard\Contracts\DashboardWidget;
use Carbon\CarbonImmutable;

/**
 * Where the current payroll period stands and how much it pays out (Blueprint §11, §14).
 *
 * Totals follow the finalized formula: additions are additional pay plus approved overtime,
 * deductions are absences, lates/undertime, cash advance repayments, device deductions and
 * other approved deductions. Tithes and devotional fines never appear here.
 */
class PayrollOverviewWidget implements DashboardWidget
{
    public function __construct(
        private readonly PayrollPeriod $period,
        private readonly CarbonImmutable $today,
    ) {}

    public function key(): string
    {
        return 'payroll';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        // An unsaved (projected) period has not been opened for verification yet.
        $currentStep = ($this->period->exists ? $this->period->status : PayrollPeriodStatus::Open)->step();

        return [
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->period_name,
                'startDate' => $this->period->start_date->toDateString(),
                'endDate' => $this->period->end_date->toDateString(),
                'cutoffDate' => $this->period->cutoff_date->toDateString(),
                'releaseDate' => $this->period->release_date->toDateString(),
                'frequency' => $this->period->pay_frequency->label(),
                'frequencyValue' => $this->period->pay_frequency->value,
                'status' => $this->period->status->value,
                'statusLabel' => $this->period->status->label(),
                'isProjected' => ! $this->period->exists,
            ],
            'daysUntilRelease' => (int) $this->today->diffInDays($this->period->release_date, false),
            'steps' => array_map(fn (PayrollPeriodStatus $status) => [
                'key' => $status->value,
                'label' => $status->label(),
                'state' => match (true) {
                    $status->step() < $currentStep => 'complete',
                    $status->step() === $currentStep => 'current',
                    default => 'upcoming',
                },
            ], PayrollPeriodStatus::cases()),
            'action' => $this->action(),
            'totals' => $this->totals(),
            'records' => $this->recordsByStatus(),
        ];
    }

    /**
     * The next step and its toggle back. A projected period isn't saved yet, so its only
     * action is to create it and open attendance verification.
     *
     * "Process payroll" stays unavailable until the Admin submits the verified period.
     *
     * @return array{label: ?string, description: ?string, blocked: ?string, revertLabel: ?string, revertDescription: ?string}
     */
    private function action(): array
    {
        $status = $this->period->exists ? $this->period->status : PayrollPeriodStatus::Open;
        $waitingForAdmin = $status === PayrollPeriodStatus::Verification && ! $this->period->isSubmittedByAdmin();

        return [
            'label' => $status->actionLabel(),
            'description' => $waitingForAdmin
                ? 'Waiting for the Admin to review the contractors\' changes and submit the verified period. Process payroll becomes available once they do.'
                : $status->actionDescription(),
            'blocked' => $waitingForAdmin ? 'Waiting for the Admin to submit the verified period' : null,
            'revertLabel' => $status->revertLabel(),
            'revertDescription' => $status->revertDescription(),
        ];
    }

    /**
     * @return array{gross: float, additions: float, deductions: float, net: float}
     */
    private function totals(): array
    {
        if (! $this->period->exists) {
            return ['gross' => 0.0, 'additions' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
        }

        $sums = $this->period->payrolls()
            ->selectRaw('
                coalesce(sum(gross_pay), 0) as gross,
                coalesce(sum(additional_pay + overtime_amount + reward_amount), 0) as additions,
                coalesce(sum(absence_deduction + late_deduction + cash_advance_deduction + device_deduction + other_deductions), 0) as deductions,
                coalesce(sum(net_pay), 0) as net
            ')
            ->toBase()
            ->first();

        return [
            'gross' => round((float) $sums->gross, 2),
            'additions' => round((float) $sums->additions, 2),
            'deductions' => round((float) $sums->deductions, 2),
            'net' => round((float) $sums->net, 2),
        ];
    }

    /**
     * @return array{total: int, byStatus: array<string, int>}
     */
    private function recordsByStatus(): array
    {
        $counts = $this->period->exists
            ? Payroll::query()->whereBelongsTo($this->period, 'period')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->toBase()
                ->pluck('total', 'status')
            : collect();

        $byStatus = [];

        foreach (PayrollStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return ['total' => array_sum($byStatus), 'byStatus' => $byStatus];
    }
}
