<?php

use App\Enums\UserRole;
use App\Http\Controllers\Employee\AttendanceController;
use App\Http\Controllers\Employee\CashAdvanceController;
use App\Http\Controllers\Employee\DashboardController;
use App\Http\Controllers\Employee\DevotionalController;
use App\Http\Controllers\Employee\LeaveRequestController;
use App\Http\Controllers\Employee\OvertimeController;
use App\Http\Controllers\Employee\PayslipController;
use App\Http\Controllers\Employee\TimeHistoryController;
use App\Http\Controllers\Employee\TimeTrackerController;
use Illuminate\Support\Facades\Route;

/*
 * Employee portal. Admins use it too: an Admin is also an employee (Admin flow §X).
 */
Route::middleware(['auth', 'verified', 'role:'.UserRole::Employee->value.','.UserRole::Admin->value, 'view:employee'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('time-tracker')->name('time-tracker.')->controller(TimeTrackerController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('start', 'start')->name('start');
            Route::post('stop-all', 'stopAll')->name('stop-all');
            Route::post('{timeLog}/break', 'toggleBreak')->name('break');
            Route::post('{timeLog}/stop', 'stop')->name('stop');
        });

        Route::get('time-history', TimeHistoryController::class)->name('time-history.index');

        Route::prefix('devotional')->name('devotionals.')->controller(DevotionalController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{devotional}/file', 'file')->name('file');
        });

        Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('verification/{period}/fix', 'verificationFix')->name('verification.fix');
            Route::post('verification/{period}/submit', 'verificationSubmit')->name('verification.submit');
        });

        Route::prefix('leave')->name('leave.')->controller(LeaveRequestController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('{leave}/cancel', 'cancel')->name('cancel');
        });

        Route::prefix('overtime')->name('overtime.')->controller(OvertimeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('{overtime}/cancel', 'cancel')->name('cancel');
        });

        Route::prefix('payslips')->name('payslips.')->controller(PayslipController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('{period}/flag', 'flag')->name('flag');
        });

        Route::prefix('cash-advances')->name('cash-advances.')->controller(CashAdvanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('{advance}/cancel', 'cancel')->name('cancel');
        });
    });
