<?php

namespace App\Services\Reports;

use App\Models\KpiRecord;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Builder;

/**
 * KPI evaluations and the rewards granted in the period.
 */
class PerformanceReport implements Report
{
    public function key(): string
    {
        return 'performance';
    }

    public function title(): string
    {
        return 'Performance & KPI Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Evaluations', 'Average KPI', 'Rating', 'Rewards', 'Incentives'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        $evaluations = $this->evaluations($filters)->with('employee:id,name')->get()->groupBy('employee_id');
        $rewards = $this->rewards($filters)->with('employee:id,name')->get()->groupBy('employee_id');

        return $evaluations->keys()->merge($rewards->keys())->unique()
            ->map(function (int $employeeId) use ($evaluations, $rewards) {
                $records = $evaluations->get($employeeId, collect());
                $granted = $rewards->get($employeeId, collect());
                $average = $records->isEmpty() ? null : round($records->avg(fn (KpiRecord $record) => (float) $record->kpi_score), 1);

                return [
                    ($records->first() ?? $granted->first())->employee->name,
                    $records->count(),
                    $average,
                    $average === null ? null : (new KpiRecord(['kpi_score' => $average]))->rating(),
                    $granted->count(),
                    round($granted->sum(fn (Reward $reward) => (float) $reward->amount), 2),
                ];
            })
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'Date', 'Record', 'Period / type', 'Score / amount', 'Remarks'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        $evaluations = $this->evaluations($filters)->with(['employee:id,name', 'period:id,period_name'])->get()
            ->map(fn (KpiRecord $record) => [
                $record->employee->name,
                $record->evaluated_at?->toDateString(),
                'KPI evaluation',
                $record->period?->period_name ?? 'General',
                (float) $record->kpi_score,
                $record->remarks,
            ]);

        $rewards = $this->rewards($filters)->with('employee:id,name')->get()
            ->map(fn (Reward $reward) => [
                $reward->employee->name,
                $reward->awarded_at?->toDateString(),
                'Reward',
                $reward->reward_type,
                $reward->amount !== null ? (float) $reward->amount : null,
                $reward->description,
            ]);

        return $evaluations->concat($rewards)->sortBy([[1, 'asc'], [0, 'asc']])->values()->all();
    }

    /**
     * @return Builder<KpiRecord>
     */
    private function evaluations(ReportFilters $filters): Builder
    {
        return KpiRecord::query()
            ->whereDate('evaluated_at', '>=', $filters->from)
            ->whereDate('evaluated_at', '<=', $filters->to)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id));
    }

    /**
     * @return Builder<Reward>
     */
    private function rewards(ReportFilters $filters): Builder
    {
        return Reward::query()
            ->whereDate('awarded_at', '>=', $filters->from)
            ->whereDate('awarded_at', '<=', $filters->to)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id));
    }
}
