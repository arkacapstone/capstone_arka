<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Appearance;
use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

#[Fillable(['employee_code', 'name', 'email', 'password', 'role', 'employment_type', 'birthday', 'phone_number', 'address', 'emergency_contact_name', 'emergency_contact_number', 'status', 'must_change_password', 'profile_completed_at', 'invitation_token', 'invitation_sent_at', 'appearance'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'invitation_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * Days an invite link stays valid before it has to be resent.
     */
    public const INVITATION_VALID_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'employment_type' => EmploymentType::class,
            'birthday' => 'date',
            'must_change_password' => 'boolean',
            'profile_completed_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'appearance' => Appearance::class,
        ];
    }

    /**
     * The next sequential company code, e.g. ARKA-0004.
     */
    public static function nextEmployeeCode(): string
    {
        $next = ((int) static::query()->max('id')) + 1;

        while (static::query()->where('employee_code', $code = sprintf('ARKA-%04d', $next))->exists()) {
            $next++;
        }

        return $code;
    }

    /**
     * Every client assignment and rate the contractor has had, including ended ones.
     *
     * @return HasMany<Rate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class, 'employee_id');
    }

    /**
     * Client assignments that have not been ended.
     *
     * @return HasMany<Rate, $this>
     */
    public function currentRates(): HasMany
    {
        return $this->rates()->current();
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'employee_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    /**
     * @return HasMany<Devotional, $this>
     */
    public function devotionals(): HasMany
    {
        return $this->hasMany(Devotional::class, 'employee_id');
    }

    /**
     * Whether the account owner still has to fill in their personal details (Complete your profile).
     */
    public function needsProfileSetup(): bool
    {
        return $this->profile_completed_at === null && ! $this->isSuperAdmin();
    }

    /**
     * Invited but has not verified their email from the invite yet, so they cannot log in.
     */
    public function isInvited(): bool
    {
        return $this->invitation_token !== null;
    }

    public function invitationExpired(): bool
    {
        return $this->invitation_sent_at === null || $this->invitation_sent_at->addDays(self::INVITATION_VALID_DAYS)->isPast();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    #[Scope]
    protected function withRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Everyone who works shifts: Contractors and Admins (an Admin is also a contractor).
     */
    #[Scope]
    protected function workforce(Builder $query): void
    {
        $query->whereIn('role', UserRole::workforceValues());
    }

    public function hasEmployeePortal(): bool
    {
        return $this->role?->hasEmployeePortal() ?? false;
    }

    /**
     * @return HasMany<TimeLog, $this>
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class, 'employee_id');
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    /**
     * @return HasMany<AttendanceCorrection, $this>
     */
    public function correctionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'employee_id');
    }

    /**
     * @return HasMany<CashAdvance, $this>
     */
    public function cashAdvances(): HasMany
    {
        return $this->hasMany(CashAdvance::class, 'employee_id');
    }

    /**
     * @return HasMany<KpiRecord, $this>
     */
    public function kpiRecords(): HasMany
    {
        return $this->hasMany(KpiRecord::class, 'employee_id');
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'employee_id');
    }

    /**
     * @return HasMany<OvertimeRequest, $this>
     */
    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'employee_id');
    }

    /**
     * @return HasMany<Payroll, $this>
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }
}
