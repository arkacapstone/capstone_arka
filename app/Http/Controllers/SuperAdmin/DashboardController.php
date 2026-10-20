<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\SuperAdminDashboard;
use App\Services\Settings\MailSettings;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SuperAdminDashboard $dashboard, MailSettings $mail): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            ...$dashboard->toArray(),
            // Each home office sends from its own Gmail; until it is set, the dashboard reminds the Super Admin.
            'mailConfigured' => $mail->isConfigured(),
        ]);
    }
}
