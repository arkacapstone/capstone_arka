<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AttendanceStatus;
use App\Enums\CashAdvanceStatus;
use App\Enums\PayrollStatus;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The shared report screens: a list of reports, then one report with filters, Print / PDF and CSV.
 * `$routes` names the index and show routes of the side the report is opened from.
 */
trait ShowsReports
{
    /**
     * @param  array<string, Report>  $reports
     * @param  array{index: string, show: string}  $routes
     */
    protected function renderIndex(array $reports, array $routes): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'reports' => array_values(array_map(fn (Report $report) => [
                'key' => $report->key(),
                'title' => $report->title(),
            ], $reports)),
            'routes' => $routes,
        ]);
    }

    /**
     * @param  array{index: string, show: string}  $routes
     */
    protected function renderReport(Request $request, Report $report, array $routes): Response|StreamedResponse
    {
        $filters = ReportFilters::fromInput($request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'employee' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
        ]));

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $filters);
        }

        return Inertia::render('Admin/Reports/Show', [
            'report' => ['key' => $report->key(), 'title' => $report->title()],
            'filters' => $filters->toArray(),
            'summary' => ['columns' => $report->summaryColumns(), 'rows' => $report->summaryRows($filters)],
            'details' => ['columns' => $report->detailColumns(), 'rows' => $report->detailRows($filters)],
            'employees' => User::query()->workforce()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])->all(),
            'statusOptions' => $this->statusOptions($report),
            'generatedAt' => now()->toIso8601String(),
            'routes' => $routes,
        ]);
    }

    private function csv(Report $report, ReportFilters $filters): StreamedResponse
    {
        $filename = str($report->title())->slug()."-{$filters->from->toDateString()}-to-{$filters->to->toDateString()}.csv";

        return response()->streamDownload(function () use ($report, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$report->title(), "{$filters->from->toDateString()} to {$filters->to->toDateString()}"]);
            fputcsv($out, []);
            fputcsv($out, ['Summary']);
            fputcsv($out, $report->summaryColumns());
            foreach ($report->summaryRows($filters) as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);
            fputcsv($out, ['Details']);
            fputcsv($out, $report->detailColumns());
            foreach ($report->detailRows($filters) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(Report $report): array
    {
        return match ($report->key()) {
            'attendance' => AttendanceStatus::options(),
            'leave' => [['value' => 'paid', 'label' => 'Paid leave'], ['value' => 'unpaid', 'label' => 'Unpaid leave']],
            'devotional' => [['value' => 'submitted', 'label' => 'Submitted'], ['value' => 'not_submitted', 'label' => 'Not submitted']],
            'payroll' => array_map(fn (PayrollStatus $status) => ['value' => $status->value, 'label' => ucfirst($status->value)], PayrollStatus::cases()),
            'cash-advance' => array_map(fn (CashAdvanceStatus $status) => ['value' => $status->value, 'label' => $status->label()], CashAdvanceStatus::cases()),
            'workforce', 'schedule' => [['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']],
            default => [],
        };
    }
}
