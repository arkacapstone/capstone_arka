<?php

namespace App\Http\Controllers\SuperAdmin\Workforce;

use App\Actions\Accounts\ChangeAccountStatus;
use App\Actions\Accounts\InvitedAccount;
use App\Actions\Accounts\SendInvitation;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Behaviour shared by every Workforce Management account list: search,
 * status counts, activation, and resending invites. A forgotten password is reset by the
 * account owner from "Forgot your password?" on the login page.
 */
abstract class AccountController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * The role this controller manages.
     */
    abstract protected function role(): UserRole;

    public function updateStatus(Request $request, User $account, ChangeAccountStatus $changeStatus): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(UserStatus::class)]]);
        $status = UserStatus::from($validated['status']);

        $changeStatus->handle($this->managed($account), $status);

        $verb = $status === UserStatus::Active ? 'activated' : 'deactivated';

        return back()->with('success', "{$this->role()->label()} account {$verb}.");
    }

    /**
     * Emails a new default password and verify link to an account that has not verified its email yet. The old ones stop working.
     */
    public function resendInvitation(User $account, SendInvitation $sendInvitation): RedirectResponse
    {
        $account = $this->managed($account);

        abort_unless($account->isInvited(), 404);

        $invitation = $sendInvitation->handle($account);

        return $this->withInvitation(back(), new InvitedAccount($account, $invitation['url'], $invitation['defaultPassword'], $invitation['emailed']), 'New login details created.');
    }

    /**
     * Guards route-bound accounts so this screen only touches its own role.
     */
    protected function managed(User $account): User
    {
        abort_unless($account->role === $this->role(), 404);

        return $account;
    }

    /**
     * @param  array{search?: ?string, status?: ?string}  $filters
     * @return Builder<User>
     */
    protected function filteredAccounts(array $filters): Builder
    {
        return User::query()
            ->withRole($this->role())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    protected function statusCounts(): array
    {
        /** @var Collection<string, int> $counts */
        $counts = User::query()
            ->withRole($this->role())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) ($counts[UserStatus::Active->value] ?? 0),
            'inactive' => (int) ($counts[UserStatus::Inactive->value] ?? 0),
        ];
    }

    /**
     * Confirms the invite went out. If the email failed, the link is shown once so it can be shared another way.
     */
    protected function withInvitation(RedirectResponse $response, InvitedAccount $invited, string $message): RedirectResponse
    {
        if ($invited->emailed) {
            return $response->with('success', "{$message} Login details sent to {$invited->user->email}.");
        }

        return $response
            ->with('success', $message)
            ->with('warning', 'The invite email could not be sent. Copy the login details below and share them privately.')
            ->with('invitation', [
                'name' => $invited->user->name,
                'email' => $invited->user->email,
                'employeeCode' => $invited->user->employee_code,
                'defaultPassword' => $invited->defaultPassword,
                'url' => $invited->invitationUrl,
            ]);
    }
}
