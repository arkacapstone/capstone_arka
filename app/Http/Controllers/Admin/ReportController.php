<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ShowsReports;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Reports (Admin flow §VII). Read-only; PDF via the browser's print dialog, CSV via download.
 */
class ReportController extends Controller
{
    use ShowsReports;

    private const ROUTES = ['index' => 'admin.reports.index', 'show' => 'admin.reports.show'];

    public function __construct(private readonly ReportRegistry $reports) {}

    public function index(): Response
    {
        return $this->renderIndex($this->reports->all(), self::ROUTES);
    }

    public function show(Request $request, string $report): Response|StreamedResponse
    {
        return $this->renderReport($request, $this->reports->find($report), self::ROUTES);
    }
}
