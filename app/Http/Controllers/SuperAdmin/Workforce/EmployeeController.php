<?php

namespace App\Http\Controllers\SuperAdmin\Workforce;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\UpdateAccount;
use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\SuperAdmin\EmployeeRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\RateResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workforce Management → Employee/Contractor management (Blueprint §3.1, §5, §19).
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
            'client' => ['nullable', 'integer'],
        ]);

        $employees = $this->filteredAccounts($filters)
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('employment_type', $type))
            ->when($filters['client'] ?? null, fn (Builder $query, int $client) => $query->whereHas(
                'currentRates', fn (Builder $query) => $query->where('client_id', $client)
            ))
            ->with('currentRates.client')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $typeCounts = User::query()
            ->withRole(UserRole::Employee)
            ->selectRaw('employment_type, count(*) as total')
            ->groupBy('employment_type')
            ->toBase()
            ->pluck('total', 'employment_type');

        return Inertia::render('SuperAdmin/Workforce/Employees', [
            'employees' => AccountResource::collection($employees),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'type' => $filters['type'] ?? '',
                'client' => $filters['client'] ?? '',
            ],
            'counts' => [
                ...$this->statusCounts(),
                'fullTime' => (int) ($typeCounts[EmploymentType::FullTime->value] ?? 0),
                'partTime' => (int) ($typeCounts[EmploymentType::PartTime->value] ?? 0),
            ],
            'clients' => $this->clientOptions(),
            'employmentTypes' => EmploymentType::options(),
        ]);
    }

    public function show(Request $request, User $account): Response
    {
        // An Admin is also a contractor, so the Super Admin assigns clients and rates to Admins here too.
        abort_unless($account->hasEmployeePortal(), 404);
        $employee = $account->load('currentRates.client');

        $history = $employee->rates()
            ->whereNotNull('end_date')
            ->with('client')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('SuperAdmin/Workforce/EmployeeShow', [
            'employee' => (new AccountResource($employee))->resolve($request),
            'section' => $employee->isAdmin() ? 'admins' : 'employees',
            'rateHistory' => RateResource::collection($history),
            'clients' => $this->clientOptions(),
            'employmentTypes' => EmploymentType::options(),
            'payFrequencies' => array_map(fn (PayFrequency $frequency) => [
                'value' => $frequency->value,
                'label' => $frequency->label(),
            ], PayFrequency::cases()),
        ]);
    }

    public function store(EmployeeRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $invited = $createAccount->handle(UserRole::Employee, $request->validated());

        return $this->withInvitation(
            to_route('super-admin.workforce.employees.show', $invited->user),
            $invited,
            'Contractor invited. An Admin gives them a client and schedule; you approve it in Requests & Approvals.',
        );
    }

    public function update(EmployeeRequest $request, User $account, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle($this->managed($account), $request->validated());

        return back()->with('success', 'Contractor details saved.');
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function clientOptions(): array
    {
        return Client::query()
            ->active()
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'client_code'])
            ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->client_name, 'code' => $client->client_code])
            ->all();
    }
}
