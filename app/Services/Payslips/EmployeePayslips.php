<?php

namespace App\Services\Payslips;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A contractor's payslips (Contractor flow §VIII): one per payroll period, fully itemized: Gross Pay for
 * the top-paying client and Additional Pay for the others. Per the Blueprint, payslips carry no SSS, PhilHealth,
 * Pag-IBIG or withholding tax — only the approved ARKA deductions.
 */
class EmployeePayslips
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function for(User $employee): Collection
    {
        return $employee->payrolls()
            ->with(['period', 'client:id,client_name', 'rate', 'rewards'])
            ->get()
            ->groupBy('period_id')
            ->map(fn (Collection $rows) => $this->present($rows))
            ->sortByDesc('periodEnd')
            ->values();
    }

    public function isReleased(PayrollPeriod $period, Collection $rows): bool
    {
        // A held payroll stays unreleased until the Super Admin lifts the hold.
        if ($this->isHeld($rows)) {
            return false;
        }

        return $period->status === PayrollPeriodStatus::Released
            || $rows->every(fn (Payroll $row) => $row->status === PayrollStatus::Released);
    }

    /**
     * @param  Collection<int, Payroll>  $rows
     */
    public function isHeld(Collection $rows): bool
    {
        return $rows->contains(fn (Payroll $row) => $row->held_at !== null);
    }

    /**
     * Earnings per client and combined deductions, as in the Payroll Formula & Scenario Reference:
     * the client that pays the most is the Gross Pay line and every other client (Full-Time or
     * Part-Time) is Additional Pay. Absences and Late/Undertime are one total each, not split by client.
     *
     * @param  Collection<int, Payroll>  $rows
     * @return array<string, mixed>
     */
    public function present(Collection $rows, bool $preview = false): array
    {
        $period = $rows->first()->period;
        $available = $this->isReleased($period, $rows);
        // Contractors only see figures once released; the Super Admin can preview them before.
        $shown = $available || $preview;
        $sum = fn (string $column) => round((float) $rows->sum(fn (Payroll $row) => (float) $row->{$column}), 2);
        $rows = $rows->sortByDesc(fn (Payroll $row) => (float) $row->gross_pay)->values();
        $client = fn (Payroll $row) => $row->client?->client_name.($row->rate?->employment_type ? ' · '.$row->rate->employment_type->label() : '');
        $hourly = fn (Payroll $row) => '₱'.number_format((float) $row->hourly_rate, 2).'/hr';

        $earnings = [
            ...$rows->map(fn (Payroll $row, int $index) => [
                'label' => ($index === 0 ? 'Gross Pay' : 'Additional Pay')." ({$client($row)})",
                'amount' => (float) $row->gross_pay,
            ])->all(),
            // Rewards with an amount (Performance & Rewards) are Additional Pay too.
            ...$rows->flatMap(fn (Payroll $row) => $row->rewards)->map(fn (Reward $reward) => [
                'label' => "Additional Pay (Reward · {$reward->reward_type})",
                'amount' => (float) $reward->amount,
                'detail' => $reward->description,
            ])->values()->all(),
            ...$rows->filter(fn (Payroll $row) => (float) $row->additional_pay > 0)->map(fn (Payroll $row) => [
                'label' => "Additional Hours Pay ({$row->client?->client_name})",
                'amount' => (float) $row->additional_pay,
                'detail' => self::duration($row->additional_minutes)." @ {$hourly($row)}",
            ])->all(),
            ...$rows->filter(fn (Payroll $row) => (float) $row->overtime_amount > 0)->map(fn (Payroll $row) => [
                'label' => "Overtime Pay ({$row->client?->client_name})",
                'amount' => (float) $row->overtime_amount,
                'detail' => "Approved · @ {$hourly($row)}",
            ])->all(),
        ];

        $daysAbsent = (float) $rows->sum(fn (Payroll $row) => (float) $row->days_absent);
        $lateMinutes = (int) $rows->sum('late_minutes');

        $deductions = array_values(array_filter([
            ['label' => 'Absences', 'amount' => $sum('absence_deduction'), 'detail' => $daysAbsent > 0 ? rtrim(rtrim(number_format($daysAbsent, 1), '0'), '.').' '.($daysAbsent == 1 ? 'day' : 'days') : null],
            ['label' => 'Late / Undertime', 'amount' => $sum('late_deduction'), 'detail' => $lateMinutes > 0 ? self::duration($lateMinutes) : null],
            ['label' => 'Cash Advance Repayment', 'amount' => $sum('cash_advance_deduction'), 'detail' => 'Given before payday, deducted in full'],
            ['label' => 'Device', 'amount' => $sum('device_deduction')],
            ['label' => 'Other approved deductions', 'amount' => $sum('other_deductions')],
        ], fn (array $line) => $line['amount'] > 0));

        return [
            'periodId' => $period->id,
            'period' => $period->period_name,
            'periodStart' => $period->start_date->toDateString(),
            'periodEnd' => $period->end_date->toDateString(),
            'issued' => $period->release_date?->toDateString(),
            'status' => $available ? 'available' : ($this->isHeld($rows) ? 'on_hold' : 'processing'),
            'net' => $shown ? $sum('net_pay') : null,
            'earnings' => $shown ? $earnings : [],
            'grossTotal' => $shown ? round(array_sum(array_column($earnings, 'amount')), 2) : null,
            'deductions' => $shown ? $deductions : [],
            'deductionsTotal' => $shown ? round(array_sum(array_column($deductions, 'amount')), 2) : null,
        ];
    }

    /**
     * 90 → "1 hr 30 min", 16 → "16 min", 960 → "16 hrs".
     */
    private static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? $hours.' '.($hours === 1 ? 'hr' : 'hrs') : '').($rest ? " {$rest} min" : ''));
    }
}
