<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\HomeOffice;
use App\Models\User;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Register your home office: a new workforce company signs up from the login page. It gets
 * its own home office and its first account, the Super Admin, who then adds the Admins and
 * Contractors. Each home office only sees its own records (see HomeOffice::scopeId()).
 */
class RegisteredUserController extends Controller
{
    /**
     * The login page with the register card open.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'startWith' => 'register',
        ]);
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'home_office_name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                // Names stay unique in any letter case. No one is signed in, so this sees every home office.
                if (HomeOffice::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->exists()) {
                    $fail('A home office with this name is already registered.');
                }
            }],
            'home_office_address' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], ['home_office_name' => 'home office name', 'home_office_address' => 'address', 'name' => 'full name']);

        // Both rows or neither: a home office never exists without its Super Admin.
        $user = DB::transaction(function () use ($validated) {
            $homeOffice = HomeOffice::query()->create([
                'name' => trim($validated['home_office_name']),
                'address' => $validated['home_office_address'] ?? null,
            ]);

            return User::query()->create([
                'home_office_id' => $homeOffice->id,
                // The first account of a home office; codes are unique per home office.
                'employee_code' => 'ARKA-0001',
                'role' => UserRole::SuperAdmin,
                'status' => UserStatus::Active->value,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $activity->log('workforce', 'Registered home office', $user->homeOffice, $user->homeOffice->name);

        return redirect(route('dashboard', absolute: false));
    }
}
