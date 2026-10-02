<?php

namespace App\Http\Controllers\Employee;

use App\Actions\TimeTracking\ManageTimer;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\EmployeeDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EmployeeDashboard $dashboard, ManageTimer $timers): Response
    {
        $timers->stopFinishedShifts($request->user());

        return Inertia::render('Employee/Dashboard', $dashboard->toArray($request->user()));
    }
}
