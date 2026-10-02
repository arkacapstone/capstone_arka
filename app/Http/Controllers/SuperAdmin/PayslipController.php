<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Services\Payslips\EmployeePayslips;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Payslips (Blueprint §3.1): every payslip generated from finalized payroll,
 * one per contractor per period, itemized exactly as the contractor sees it.
 */
class PayslipController extends Controller
{
    public function __invoke(Request $request, EmployeePayslips $payslips): Response
    {
        $filters = $request->validate([
            'period' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:available,processing'],
        ]);

        // Payslips exist once payroll is calculated (attendance locked) and become available on release.
        $periods = PayrollPeriod::query()
            ->whereIn('status', [PayrollPeriodStatus::Locked, PayrollPeriodStatus::Processed, PayrollPeriodStatus::Released])
            ->orderByDesc('start_date')
            ->get();

        $periodId = (int) ($filters['period'] ?? $periods->first()?->id);

        $slips = Payroll::query()
            ->where('period_id', $periodId)
            ->with(['period', 'client:id,client_name', 'rate', 'rewards', 'employee:id,name,employee_code,employment_type,role'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
                'employee',
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%"),
            ))
            ->get()
            ->groupBy('employee_id')
            ->map(function (Collection $rows) use ($payslips) {
                $employee = $rows->first()->employee;

                return [
                    ...$payslips->present($rows, preview: true),
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'code' => $employee->employee_code,
                        'type' => $employee->employment_type?->label(),
                        'role' => $employee->role->label(),
                    ],
                    'clients' => $rows->pluck('client.client_name')->filter()->values()->all(),
                    'gross' => round((float) $rows->sum('gross_pay'), 2),
                    'net' => round((float) $rows->sum('net_pay'), 2),
                ];
            })
            ->when($filters['status'] ?? null, fn (Collection $slips, string $status) => $slips->where('status', $status))
            ->sortBy('employee.name')
            ->values();

        $period = $periods->firstWhere('id', $periodId);

        return Inertia::render('SuperAdmin/Payslips/Index', [
            'periods' => $periods->map(fn (PayrollPeriod $period) => [
                'value' => $period->id,
                'label' => "{$period->period_name} · {$period->status->label()}",
            ])->all(),
            'period' => $period ? [
                'id' => $period->id,
                'name' => $period->period_name,
                'status' => $period->status->value,
                'statusLabel' => $period->status->label(),
                'released' => $period->status === PayrollPeriodStatus::Released,
                'releaseDate' => $period->release_date->toDateString(),
            ] : null,
            'payslips' => $slips->all(),
            'summary' => [
                'count' => $slips->count(),
                'gross' => round($slips->sum('gross'), 2),
                'net' => round($slips->sum('net'), 2),
            ],
            'filters' => [
                'period' => $period?->id ?? '',
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }
}
