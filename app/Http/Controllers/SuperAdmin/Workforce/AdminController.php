<?php

namespace App\Http\Controllers\SuperAdmin\Workforce;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\UpdateAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\SuperAdmin\AccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workforce Management → Admin management (Blueprint §3.1).
 */
class AdminController extends AccountController
{
    protected function role(): UserRole
    {
        return UserRole::Admin;
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ]);

        return Inertia::render('SuperAdmin/Workforce/Admins', [
            'admins' => AccountResource::collection(
                $this->filteredAccounts($filters)->paginate(self::PER_PAGE)->withQueryString()
            ),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'counts' => $this->statusCounts(),
        ]);
    }

    public function store(AccountRequest $request, CreateAccount $createAccount): RedirectResponse
    {
        $invited = $createAccount->handle(UserRole::Admin, $request->validated());

        return $this->withInvitation(to_route('super-admin.workforce.admins.index'), $invited, 'Admin invited.');
    }

    public function update(AccountRequest $request, User $account, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle($this->managed($account), $request->validated());

        return back()->with('success', 'Admin details saved.');
    }
}
