<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Appearance;
use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\BelongsToHomeOffice;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * A login account. Personal details (birthday, phone, address, emergency contact, photo,
 * profile completed) live in user_profiles; they are still read and set here as
 * $user->birthday etc. and are saved to the profile together with the user.
 *
 * Admins and Contractors work shifts, so each gets a contractors row (same id as the user).
 */
#[Fillable(['home_office_id', 'employee_code', 'name', 'email', 'password', 'role', 'birthday', 'phone_number', 'address', 'emergency_contact_name', 'emergency_contact_number', 'profile_photo_path', 'status', 'must_change_password', 'profile_completed_at', 'invitation_token', 'invitation_sent_at', 'appearance'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'invitation_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToHomeOffice, HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * Days an invite link stays valid before it has to be resent.
     */
    public const INVITATION_VALID_DAYS = 7;

    /**
     * Profile values set on the user, written to user_profiles when the user is saved.
     *
     * @var array<string, mixed>
     */
    protected array $pendingProfile = [];

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
            'must_change_password' => 'boolean',
            'invitation_sent_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'appearance' => Appearance::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->role?->hasEmployeePortal()) {
                Contractor::ensureFor($user->id);
            }

            if ($user->pendingProfile !== [] || $user->wasRecentlyCreated) {
                $profile = $user->profile()->updateOrCreate([], $user->pendingProfile);
                $user->pendingProfile = [];
                $user->setRelation('profile', $profile);
            }
        });
    }

    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * @return HasOne<Contractor, $this>
     */
    public function contractor(): HasOne
    {
        return $this->hasOne(Contractor::class);
    }

    /**
     * The contractor's client assignments (contractor_id is the user's id).
     *
     * @return HasMany<ClientAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ClientAssignment::class, 'contractor_id');
    }

    /**
     * Reads a profile value, including one set on this user but not saved yet.
     */
    private function profileValue(string $field): mixed
    {
        if (array_key_exists($field, $this->pendingProfile)) {
            return (new UserProfile([$field => $this->pendingProfile[$field]]))->getAttribute($field);
        }

        return $this->profile?->getAttribute($field);
    }

    private function profileAttribute(string $field): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profileValue($field),
            set: function ($value) use ($field) {
                $this->pendingProfile[$field] = $value;

                return [];
            },
        );
    }

    protected function birthday(): Attribute
    {
        return $this->profileAttribute('birthday');
    }

    protected function phoneNumber(): Attribute
    {
        return $this->profileAttribute('phone_number');
    }

    protected function address(): Attribute
    {
        return $this->profileAttribute('address');
    }

    protected function emergencyContactName(): Attribute
    {
        return $this->profileAttribute('emergency_contact_name');
    }

    protected function emergencyContactNumber(): Attribute
    {
        return $this->profileAttribute('emergency_contact_number');
    }

    protected function profilePhotoPath(): Attribute
    {
        return $this->profileAttribute('profile_photo_path');
    }

    protected function profileCompletedAt(): Attribute
    {
        return $this->profileAttribute('profile_completed_at');
    }

    /**
     * Full-Time / Part-Time follows the contractor's open client assignments: any Full-Time
     * client makes them Full-Time, otherwise a Part-Time client makes them Part-Time.
     */
    protected function employmentType(): Attribute
    {
        return Attribute::get(function () {
            $types = $this->relationLoaded('assignments')
                ? $this->assignments->whereNull('end_date')->pluck('employment_type')
                : $this->assignments()->whereNull('end_date')->get()->pluck('employment_type');

            return match (true) {
                $types->contains(EmploymentType::FullTime) => EmploymentType::FullTime,
                $types->contains(EmploymentType::PartTime) => EmploymentType::PartTime,
                default => null,
            };
        });
    }

    /**
     * Contractors classified Full-Time or Part-Time (see employmentType()).
     */
    #[Scope]
    protected function ofEmploymentType(Builder $query, EmploymentType|string $type): void
    {
        $type = $type instanceof EmploymentType ? $type : EmploymentType::from($type);
        $openOfType = fn (EmploymentType $of) => fn (Builder $assignments) => $assignments->whereNull('end_date')->where('employment_type', $of);

        $query->whereHas('assignments', $openOfType($type));

        if ($type === EmploymentType::PartTime) {
            $query->whereDoesntHave('assignments', $openOfType(EmploymentType::FullTime));
        }
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
     * @return HasManyThrough<Rate, ClientAssignment, $this>
     */
    public function rates(): HasManyThrough
    {
        return $this->throughAssignments(Rate::class);
    }

    /**
     * Client assignments that have not been ended.
     *
     * @return HasManyThrough<Rate, ClientAssignment, $this>
     */
    public function currentRates(): HasManyThrough
    {
        return $this->rates()->current();
    }

    /**
     * @return HasManyThrough<Schedule, ClientAssignment, $this>
     */
    public function schedules(): HasManyThrough
    {
        return $this->throughAssignments(Schedule::class);
    }

    /**
     * @return HasManyThrough<Attendance, ClientAssignment, $this>
     */
    public function attendances(): HasManyThrough
    {
        return $this->throughAssignments(Attendance::class);
    }

    /**
     * @return HasMany<Devotional, $this>
     */
    public function devotionals(): HasMany
    {
        return $this->hasMany(Devotional::class, 'contractor_id');
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
     * @return HasManyThrough<TimeLog, ClientAssignment, $this>
     */
    public function timeLogs(): HasManyThrough
    {
        return $this->throughAssignments(TimeLog::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'contractor_id');
    }

    /**
     * @return HasManyThrough<AttendanceCorrection, ClientAssignment, $this>
     */
    public function correctionRequests(): HasManyThrough
    {
        return $this->throughAssignments(AttendanceCorrection::class);
    }

    /**
     * @return HasMany<CashAdvance, $this>
     */
    public function cashAdvances(): HasMany
    {
        return $this->hasMany(CashAdvance::class, 'contractor_id');
    }

    /**
     * @return HasMany<KpiRecord, $this>
     */
    public function kpiRecords(): HasMany
    {
        return $this->hasMany(KpiRecord::class, 'contractor_id');
    }

    /**
     * Rewards come from KPI records, so they are reached through them.
     *
     * @return HasManyThrough<Reward, KpiRecord, $this>
     */
    public function rewards(): HasManyThrough
    {
        return $this->hasManyThrough(Reward::class, KpiRecord::class, 'contractor_id', 'kpi_id');
    }

    /**
     * @return HasManyThrough<OvertimeRequest, ClientAssignment, $this>
     */
    public function overtimeRequests(): HasManyThrough
    {
        return $this->throughAssignments(OvertimeRequest::class);
    }

    /**
     * @return HasManyThrough<Payroll, ClientAssignment, $this>
     */
    public function payrolls(): HasManyThrough
    {
        return $this->throughAssignments(Payroll::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }

    /**
     * Rows of a table that points to client_assignments, for this contractor.
     *
     * @template TRelated of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelated>  $related
     * @return HasManyThrough<TRelated, ClientAssignment, $this>
     */
    private function throughAssignments(string $related): HasManyThrough
    {
        return $this->hasManyThrough($related, ClientAssignment::class, 'contractor_id', 'assignment_id');
    }
}
