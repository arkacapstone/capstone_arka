<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\ClientAssignmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DevotionalController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use Illuminate\Support\Facades\Route;

/*
 * Admin: day-to-day workforce operations. No money-related modules (Blueprint §2, §21).
 */
Route::middleware(['auth', 'verified', 'role:'.UserRole::Admin->value, 'view:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('employees')->name('employees.')->controller(EmployeeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{account}', 'show')->name('show');
            Route::put('{account}', 'update')->name('update');
            Route::patch('{account}/status', 'updateStatus')->name('status');
            Route::post('{account}/invitation', 'resendInvitation')->name('invitation');
        });

        // Scheduling has two tabs: Clients (given here, approved by the Super Admin) and Schedules.
        Route::prefix('scheduling/clients')->name('scheduling.clients.')->controller(ClientAssignmentController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('requests/{assignmentRequest}/cancel', 'cancel')->name('cancel');
        });

        Route::prefix('scheduling')->name('scheduling.')->controller(ScheduleController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{schedule}', 'update')->name('update');
            Route::patch('{schedule}/status', 'updateStatus')->name('status');
        });

        Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('corrections', 'correct')->name('correct');
            Route::post('requests/{correction}/approve', 'approve')->name('requests.approve');
            Route::post('requests/{correction}/reject', 'reject')->name('requests.reject');
            Route::get('requests/{correction}/proof', 'proof')->name('requests.proof');
        });

        Route::prefix('devotionals')->name('devotionals.')->controller(DevotionalController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('employees/{account}', 'history')->name('history');
            Route::get('{devotional}/file', 'file')->name('file');
        });

        Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{report}', 'show')->name('show');
        });
    });
