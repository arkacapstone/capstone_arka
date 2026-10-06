<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Auth\ProfileSetupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\ActivityLogController;
use App\Http\Controllers\SuperAdmin\AnnouncementController;
use App\Http\Controllers\SuperAdmin\CashAdvanceController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\MailSettingsController;
use App\Http\Controllers\SuperAdmin\PayrollController;
use App\Http\Controllers\SuperAdmin\PayslipController as SuperAdminPayslipController;
use App\Http\Controllers\SuperAdmin\PerformanceController;
use App\Http\Controllers\SuperAdmin\ReportController as SuperAdminReportController;
use App\Http\Controllers\SuperAdmin\RequestController;
use App\Http\Controllers\SuperAdmin\RuleController;
use App\Http\Controllers\SuperAdmin\Workforce\AdminController;
use App\Http\Controllers\SuperAdmin\Workforce\ClientController;
use App\Http\Controllers\SuperAdmin\Workforce\DeviceController;
use App\Http\Controllers\SuperAdmin\Workforce\EmployeeController;
use App\Http\Controllers\SuperAdmin\Workforce\EmployeeRateController;
use App\Http\Controllers\ViewModeController;
use App\Support\ViewMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// No public landing page: guests go to Login, signed-in users to their own dashboard.
Route::get('/', function (Request $request) {
    return $request->user() ? to_route('dashboard') : to_route('login');
})->name('home');

/*
 * One login for everyone; after sign-in each role lands on its own dashboard (Admin flow §I).
 */
Route::get('/dashboard', function (Request $request) {
    return to_route(ViewMode::homeRoute($request));
})->middleware(['auth', 'verified'])->name('dashboard');

// Dashboard Switcher: Admin view ↔ the Admin's own Employee view (Admin flow §X).
Route::post('/view-mode', ViewModeController::class)
    ->middleware(['auth', 'verified', 'role:'.UserRole::Admin->value])
    ->name('view-mode.switch');

Route::middleware(['auth', 'verified', 'role:'.UserRole::SuperAdmin->value])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('dashboard', SuperAdminDashboardController::class)->name('dashboard');

        Route::get('workforce', fn () => to_route('super-admin.workforce.admins.index'))->name('workforce');
        Route::prefix('workforce/admins')->name('workforce.admins.')->controller(AdminController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{account}', 'update')->name('update');
            Route::patch('{account}/status', 'updateStatus')->name('status');
            Route::post('{account}/invitation', 'resendInvitation')->name('invitation');
        });

        Route::prefix('workforce/employees')->name('workforce.employees.')->group(function () {
            Route::controller(EmployeeController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                // No store: Admins add contractors (Admin → Contractor Management); the Super Admin views and edits them.
                Route::get('{account}', 'show')->name('show');
                Route::put('{account}', 'update')->name('update');
                Route::patch('{account}/status', 'updateStatus')->name('status');
                Route::post('{account}/invitation', 'resendInvitation')->name('invitation');
            });

            Route::controller(EmployeeRateController::class)->name('rates.')->group(function () {
                Route::put('{account}/rates/{rate}', 'update')->name('update');
                Route::delete('{account}/rates/{rate}', 'destroy')->name('destroy');
            });
        });

        // View-only: Admins add clients (with their break allowance); the Super Admin approves assignments in Requests & Approvals.
        Route::get('workforce/clients', [ClientController::class, 'index'])->name('workforce.clients.index');

        // Company equipment; a lost device is deducted at its own value.
        Route::prefix('workforce/devices')->name('workforce.devices.')->controller(DeviceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{device}', 'update')->name('update');
            Route::post('{device}/assign', 'assign')->name('assign');
            Route::post('{device}/returned', 'returned')->name('returned');
            Route::post('{device}/lost', 'lost')->name('lost');
        });

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll');
        Route::prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
            Route::post('periods', 'store')->name('store');
            Route::get('periods/{period}', 'show')->name('show');
            Route::post('periods/{period}/advance', 'advance')->name('advance');
            Route::post('periods/{period}/revert', 'revert')->name('revert');
            Route::delete('periods/{period}', 'destroy')->name('destroy');
            Route::patch('rows/{payroll}', 'adjust')->name('adjust');
            Route::post('rows/{payroll}/hold', 'hold')->name('hold');
            Route::delete('rows/{payroll}/hold', 'releaseHold')->name('hold.release');
        });

        Route::get('payslips', SuperAdminPayslipController::class)->name('payslips');

        Route::get('requests', [RequestController::class, 'index'])->name('requests');
        Route::prefix('requests/leave')->name('requests.leave.')->controller(RequestController::class)->group(function () {
            Route::post('{leave}/approve', 'approve')->name('approve');
            Route::post('{leave}/reject', 'reject')->name('reject');
            Route::get('{leave}/proof', 'proof')->name('proof');
        });
        Route::prefix('requests/overtime')->name('requests.overtime.')->controller(RequestController::class)->group(function () {
            Route::post('{overtime}/approve', 'approveOvertime')->name('approve');
            Route::post('{overtime}/reject', 'rejectOvertime')->name('reject');
        });
        Route::prefix('requests/clients')->name('requests.clients.')->controller(RequestController::class)->group(function () {
            Route::post('{assignment}/approve', 'approveAssignment')->name('approve');
            Route::post('{assignment}/reject', 'rejectAssignment')->name('reject');
        });

        // Cash advances are listed under Requests & Approvals; the old address redirects there.
        Route::get('cash-advances', [CashAdvanceController::class, 'index'])->name('cash-advances');
        Route::prefix('cash-advances')->name('cash-advances.')->controller(CashAdvanceController::class)->group(function () {
            Route::post('{advance}/approve', 'approve')->name('approve');
            Route::post('{advance}/reject', 'reject')->name('reject');
        });

        Route::get('performance', [PerformanceController::class, 'index'])->name('performance');
        Route::prefix('performance')->name('performance.')->controller(PerformanceController::class)->group(function () {
            Route::post('evaluations', 'evaluate')->name('evaluate');
            Route::delete('evaluations/{kpi}', 'destroyEvaluation')->name('evaluations.destroy');
            Route::post('rewards', 'reward')->name('reward');
        });

        Route::get('rules', [RuleController::class, 'index'])->name('rules');
        Route::prefix('rules')->name('rules.')->controller(RuleController::class)->group(function () {
            Route::put('/', 'update')->name('update');
            Route::post('leave-types', 'storeLeaveType')->name('leave-types.store');
            Route::put('leave-types/{leaveType}', 'updateLeaveType')->name('leave-types.update');
        });
        Route::put('rules/mail', [MailSettingsController::class, 'update'])->name('rules.mail.update');
        Route::post('rules/mail/test', [MailSettingsController::class, 'test'])->middleware('throttle:5,1')->name('rules.mail.test');

        Route::get('reports', [SuperAdminReportController::class, 'index'])->name('reports');
        Route::get('reports/{report}', [SuperAdminReportController::class, 'show'])->name('reports.show');

        Route::get('notifications', [AnnouncementController::class, 'index'])->name('notifications');
        Route::post('notifications/announcements', [AnnouncementController::class, 'store'])->name('notifications.announce');

        Route::get('activity-logs', ActivityLogController::class)->name('activity-logs');
    });

// The invite email's "Verify email & log in" button; works whether or not someone is signed in on this browser.
Route::get('/invitation/{token}', InvitationController::class)->middleware('throttle:10,1')->name('invitation.verify');

Route::middleware('auth')->group(function () {
    Route::get('/password/setup', [PasswordSetupController::class, 'edit'])->name('password.setup');
    Route::put('/password/setup', [PasswordSetupController::class, 'update'])->name('password.setup.update');
    Route::get('/profile/setup', [ProfileSetupController::class, 'edit'])->name('profile.setup');
    Route::put('/profile/setup', [ProfileSetupController::class, 'update'])->name('profile.setup.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/security', [ProfileController::class, 'security'])->middleware('security.confirm')->name('profile.security');
    Route::get('/profile/security/confirm', [ProfileController::class, 'confirmSecurity'])->name('profile.security.confirm');
    Route::post('/profile/security/confirm', [ProfileController::class, 'unlockSecurity'])->middleware('throttle:6,1')->name('profile.security.unlock');
    Route::get('/profile/appearance', [ProfileController::class, 'appearanceSettings'])->name('profile.appearance.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/appearance', [ProfileController::class, 'appearance'])->name('profile.appearance');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

require __DIR__.'/admin.php';
require __DIR__.'/employee.php';
require __DIR__.'/auth.php';
