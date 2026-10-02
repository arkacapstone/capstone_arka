<?php

namespace App\Services\Reports;

use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Leave data is created and approved in the Super Admin-only module; this report only reads it.
 */
class LeaveReport implements Report
{
    public function key(): string
    {
        return 'leave';
    }

    public function title(): string
    {
        return 'Leave Summary';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Paid leave (days)', 'Unpaid leave (days)', 'Total (days)'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with(['employee:id,name', 'leaveType:id,is_paid'])
            ->where('status', 'approved')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($requests) use ($filters) {
                $paid = $requests->filter(fn (LeaveRequest $request) => $request->leaveType->is_paid)->sum(fn ($request) => $this->daysWithin($request, $filters));
                $unpaid = $requests->reject(fn (LeaveRequest $request) => $request->leaveType->is_paid)->sum(fn ($request) => $this->daysWithin($request, $filters));

                return [$requests->first()->employee->name, $paid, $unpaid, $paid + $unpaid];
            })
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Contractor', 'From', 'To', 'Type', 'Paid', 'Status'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with(['employee:id,name', 'leaveType:id,leave_type_name,is_paid'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveRequest $request) => [
                $request->employee->name,
                $request->start_date->toDateString(),
                $request->end_date->toDateString(),
                $request->leaveType->leave_type_name,
                $request->leaveType->is_paid ? 'Paid' : 'Unpaid',
                ucfirst(str_replace('_', ' ', $request->status->value)),
            ])
            ->all();
    }

    /**
     * Leave requests that overlap the report period.
     *
     * @return Builder<LeaveRequest>
     */
    private function query(ReportFilters $filters): Builder
    {
        return LeaveRequest::query()
            ->whereDate('start_date', '<=', $filters->to)
            ->whereDate('end_date', '>=', $filters->from)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id))
            ->when($filters->status, fn (Builder $query, string $status) => $query->whereHas(
                'leaveType', fn (Builder $query) => $query->where('is_paid', $status === 'paid')
            ));
    }

    private function daysWithin(LeaveRequest $request, ReportFilters $filters): int
    {
        $start = CarbonImmutable::parse($request->start_date->toDateString())->max($filters->from);
        $end = CarbonImmutable::parse($request->end_date->toDateString())->min($filters->to);

        return $end->lessThan($start) ? 0 : (int) $start->diffInDays($end) + 1;
    }
}
