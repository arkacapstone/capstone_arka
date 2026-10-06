<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Activity Logs (Blueprint §3.1 module 11): login records, account, attendance
 * and payroll changes, approval history and other important actions. Read-only.
 */
class ActivityLogController extends Controller
{
    public const MODULES = [
        'auth' => 'Logins',
        'workforce' => 'Accounts & workforce',
        'attendance' => 'Attendance',
        'scheduling' => 'Scheduling',
        'devotionals' => 'Devotionals',
        'requests' => 'Leave approvals',
        'cash-advances' => 'Cash advances',
        'payroll' => 'Payroll',
        'payslips' => 'Payslips',
        'performance' => 'Performance & rewards',
        'rules' => 'System & rules',
        'notifications' => 'Announcements',
    ];

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:50'],
            'user' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = ActivityLog::query()
            ->with('user:id,name,employee_code,role')
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('module', $module))
            ->when($filters['user'] ?? null, fn (Builder $query, int $user) => $query->where('user_id', $user))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('action', 'like', "%{$search}%")->orWhere('details', 'like', "%{$search}%")
            ))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->latest()
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'module' => $log->module,
                'moduleLabel' => self::MODULES[$log->module] ?? str($log->module)->headline()->toString(),
                'action' => $log->action,
                'details' => $log->details,
                'user' => $log->user ? ['name' => $log->user->name, 'code' => $log->user->employee_code, 'role' => $log->user->role?->label()] : null,
                'createdAt' => $log->created_at->toIso8601String(),
            ]);

        $modules = ActivityLog::query()->distinct()->orderBy('module')->pluck('module');

        return Inertia::render('SuperAdmin/ActivityLogs/Index', [
            'logs' => Paginated::from($logs),
            'filters' => [
                'module' => $filters['module'] ?? '',
                'user' => $filters['user'] ?? '',
                'search' => $filters['search'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'modules' => $modules->map(fn (string $module) => ['value' => $module, 'label' => self::MODULES[$module] ?? str($module)->headline()->toString()])->all(),
            'users' => User::query()->whereIn('id', ActivityLog::query()->whereNotNull('user_id')->distinct()->select('user_id'))->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])->all(),
            'summary' => [
                'today' => ActivityLog::query()->where('created_at', '>=', CarbonImmutable::today())->count(),
                'loginsToday' => ActivityLog::query()->where('module', 'auth')->where('created_at', '>=', CarbonImmutable::today())->count(),
                'thisWeek' => ActivityLog::query()->where('created_at', '>=', CarbonImmutable::today()->startOfWeek())->count(),
            ],
        ]);
    }
}
