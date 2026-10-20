<?php

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\HomeOffice;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * For the tables that point to client_assignments instead of storing the contractor and the
 * client (rates, schedules, time logs, attendance, attendance corrections, overtime, payroll).
 *
 * - employee() and client() reach the contractor's account and the client through the assignment.
 * - employee_id and client_id can still be read and set: setting them picks (or opens) the
 *   assignment for that contractor and client when the row is saved.
 * - forEmployee() / forClient() filter by contractor or client in queries.
 */
trait BelongsToAssignment
{
    protected ?int $pendingEmployeeId = null;

    protected ?int $pendingClientId = null;

    public static function bootBelongsToAssignment(): void
    {
        // Only the rows of assignments whose contractor is in the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('assignment_id'), ClientAssignment::idsInHomeOffice($homeOfficeId));
            }
        });

        static::saving(function ($model) {
            if ($model->pendingEmployeeId === null && $model->pendingClientId === null) {
                return;
            }

            $employeeId = $model->pendingEmployeeId ?? $model->assignment?->contractor_id;
            $clientId = $model->pendingClientId ?? $model->assignment?->client_id;
            $model->pendingEmployeeId = $model->pendingClientId = null;

            if ($employeeId !== null && $clientId !== null) {
                $assignment = ClientAssignment::resolve((int) $employeeId, (int) $clientId);
                $model->assignment_id = $assignment->id;
                $model->setRelation('assignment', $assignment);
            }
        });
    }

    /**
     * @return BelongsTo<ClientAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ClientAssignment::class, 'assignment_id');
    }

    /**
     * The contractor's account, through the assignment (contractor_id is the user's id).
     *
     * @return HasOneThrough<User, ClientAssignment, $this>
     */
    public function employee(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, ClientAssignment::class, 'id', 'id', 'assignment_id', 'contractor_id');
    }

    /**
     * @return HasOneThrough<Client, ClientAssignment, $this>
     */
    public function client(): HasOneThrough
    {
        return $this->hasOneThrough(Client::class, ClientAssignment::class, 'id', 'id', 'assignment_id', 'client_id');
    }

    protected function employeeId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pendingEmployeeId ?? $this->assignment?->contractor_id,
            set: function ($value) {
                $this->pendingEmployeeId = $value === null ? null : (int) $value;

                return [];
            },
        );
    }

    protected function clientId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pendingClientId ?? $this->assignment?->client_id,
            set: function ($value) {
                $this->pendingClientId = $value === null ? null : (int) $value;

                return [];
            },
        );
    }

    /**
     * Rows of these contractors (user ids: an id, a list, or a subquery of ids).
     */
    #[Scope]
    protected function forEmployee(Builder $query, mixed $employees): void
    {
        $query->whereIn($this->qualifyColumn('assignment_id'), ClientAssignment::idsFor(contractors: self::idList($employees)));
    }

    /**
     * Rows for these clients (an id, a list, or a subquery of ids).
     */
    #[Scope]
    protected function forClient(Builder $query, mixed $clients): void
    {
        $query->whereIn($this->qualifyColumn('assignment_id'), ClientAssignment::idsFor(clients: self::idList($clients)));
    }

    /**
     * Rows for this contractor and client pair.
     */
    #[Scope]
    protected function forPair(Builder $query, int $employeeId, int $clientId): void
    {
        $query->whereIn($this->qualifyColumn('assignment_id'), ClientAssignment::idsFor($employeeId, $clientId));
    }

    private static function idList(mixed $ids): mixed
    {
        return is_int($ids) || is_string($ids) ? [(int) $ids] : $ids;
    }
}
