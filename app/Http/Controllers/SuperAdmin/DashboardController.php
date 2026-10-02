<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\SuperAdminDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SuperAdminDashboard $dashboard): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', $dashboard->toArray());
    }
}
