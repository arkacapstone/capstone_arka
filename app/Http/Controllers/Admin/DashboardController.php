<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboard $dashboard): Response
    {
        return Inertia::render('Admin/Dashboard', $dashboard->toArray());
    }
}
