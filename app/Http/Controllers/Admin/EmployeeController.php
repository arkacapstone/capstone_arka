<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\UpdateAccount;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\SuperAdmin\Workforce\AccountController;
use App\Http\Requests\Admin\EmployeeAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\ScheduleResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Contractor Management (Admin flow §III). Admins create and maintain Contractor accounts only;
 * Admin accounts, clients and rates stay with the Super Admin (Blueprint §2).
 */
class EmployeeController extends AccountController
{
    protected function role(): UserRole
    {
        return UserRole::Employee;
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'type' => ['nullable', Rule::enum(EmploymentType::class)],
        ]);

        $employees = $this->filteredAccounts($filters)
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('employment_type', $type))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Employees/Index', [
            'employees' => AccountResource::collection($employees),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'type' => $filters['type'] ?? '',
            ],
            'counts' => [
                ...$this->statusCounts(),
                'newThisWeek' => User::query()->withRole(UserRole::Employee)->where('created_at', '>=', CarbonImmutable::today()->startOfWeek())->count(),
            ],
            'employmentTypes' => EmploymentType::options(),
        ]);
    }

    public function show(Request $request, User $account): Response
    {
        $employee = $this->managed($account);
        $monthStart = CarbonImmutable::today()->startOfMonth();

        $schedules = $employee->schedules()
            ->with('client:id,client_name,client_code,break_allowance_minutes')
            ->orderByRaw('end_date is not null')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $monthCounts = $employee->attendances()
            ->whereDate('date', '>=', $monthStart)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        return Inertia::render('Admin/Employees/Show', [
            'employee' => (new AccountResource($employee))->resolve($request),
            'clients' => $employee->currentRates()->with('client:id,client_name')->get()->pluck('client.client_name')->all(),
            'schedules' => ScheduleResource::collection($schedules)->resolve($request),
            'attendanceMonth' => [
                'label' => $monthStart->format('F Y'),
                'counts' => array_map(fn (AttendanceStatus $status) => [
                    'key' => $status->value,
                    'label' => $status->label(),
                    'count' => (int) ($monthCounts[$status->value] ?? 0),
                ], AttendanceStatus::cases()),
            ],
            'employmentTypes' => EmploymentType::options(),
        ]);
    }

    public function store(EmployeeAccountRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $invited = $createAccount->handle(UserRole::Employee, $request->validated());

        return $this->withInvitation(to_route('admin.employees.show', $invited->user), $invited, 'Contractor invited.');
    }

    public function update(EmployeeAccountRequest $request, User $account, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle($this->managed($account), $request->validated());

        return back()->with('success', 'Contractor details saved.');
    }
}
