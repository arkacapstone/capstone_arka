<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;

class AttendanceReport implements Report
{
    public function key(): string
    {
        return 'attendance';
    }

    public function title(): string
    {
        return 'Attendance Report';
    }

    public function summaryColumns(): array
    {
        return ['Contractor', 'Present', 'Late', 'Undertime', 'Absent', 'Leave', 'Incomplete'];
    }

    public function summaryRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with('employee:id,name')
            ->get(['id', 'employee_id', 'status'])
            ->groupBy('employee_id')
            ->map(function ($records) {
                $count = fn (AttendanceStatus ...$statuses) => $records->filter(fn (Attendance $row) => in_array($row->status, $statuses, true))->count();

                return [
                    $records->first()->employee->name,
                    $count(AttendanceStatus::Present),
                    $count(AttendanceStatus::Late),
                    $count(AttendanceStatus::Undertime),
                    $count(AttendanceStatus::Absent),
                    $count(AttendanceStatus::PaidLeave, AttendanceStatus::UnpaidLeave),
                    $count(AttendanceStatus::Incomplete),
                ];
            })
            ->sortBy(0)
            ->values()
            ->all();
    }

    public function detailColumns(): array
    {
        return ['Date', 'Contractor', 'Client', 'Time In', 'Time Out', 'Hours', 'Late (min)', 'Status'];
    }

    public function detailRows(ReportFilters $filters): array
    {
        return $this->query($filters)
            ->with(['employee:id,name', 'client:id,client_name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn (Attendance $row) => [
                $row->date->toDateString(),
                $row->employee->name,
                $row->client?->client_name,
                $row->time_in?->format('g:i A'),
                $row->time_out?->format('g:i A'),
                $row->actual_hours !== null ? (float) $row->actual_hours : null,
                $row->late_minutes,
                $row->status->label(),
            ])
            ->all();
    }

    /**
     * @return Builder<Attendance>
     */
    private function query(ReportFilters $filters): Builder
    {
        return Attendance::query()
            ->whereDate('date', '>=', $filters->from)
            ->whereDate('date', '<=', $filters->to)
            ->when($filters->employeeId, fn (Builder $query, int $id) => $query->where('employee_id', $id))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
