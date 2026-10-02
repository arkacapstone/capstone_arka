<?php

namespace App\Services\Reports;

/**
 * A read-only report built from records other modules create (Blueprint §17).
 * Reports are outputs; they never replace the operational records.
 */
interface Report
{
    public function key(): string;

    public function title(): string;

    /**
     * @return list<string>
     */
    public function summaryColumns(): array;

    /**
     * @return list<list<string|int|float|null>>
     */
    public function summaryRows(ReportFilters $filters): array;

    /**
     * @return list<string>
     */
    public function detailColumns(): array;

    /**
     * @return list<list<string|int|float|null>>
     */
    public function detailRows(ReportFilters $filters): array;
}
