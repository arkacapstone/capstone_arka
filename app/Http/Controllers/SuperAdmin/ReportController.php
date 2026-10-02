<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Concerns\ShowsReports;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Super Admin → Reports (Blueprint §3.1 module 9, §17): every Admin report plus the payroll-related
 * ones. Reports read the records other modules create; PDF via print, CSV via download.
 */
class ReportController extends Controller
{
    use ShowsReports;

    private const ROUTES = ['index' => 'super-admin.reports', 'show' => 'super-admin.reports.show'];

    public function __construct(private readonly ReportRegistry $reports) {}

    public function index(): Response
    {
        return $this->renderIndex($this->reports->forSuperAdmin(), self::ROUTES);
    }

    public function show(Request $request, string $report): Response|StreamedResponse
    {
        return $this->renderReport($request, $this->reports->find($report, superAdmin: true), self::ROUTES);
    }
}
