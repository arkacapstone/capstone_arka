<?php

namespace App\Models;

use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Junction between a contractor and a client: who works for which client, from when to when.
 * Rates, schedules, time logs, attendance, overtime and payroll point here instead of
 * repeating the contractor and the client.
 */
#[Fillable(['contractor_id', 'client_id', 'employment_type', 'start_date', 'end_date'])]
class ClientAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        // Only the assignments of contractors in the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('contractor_id'), HomeOffice::userIds($homeOfficeId));
            }
        });
    }

    /**
     * Subquery of the ids of the assignments in this home office (by the contractor's
     * account), for the global scope of the tables that point to an assignment.
     */
    public static function idsInHomeOffice(int $homeOfficeId): QueryBuilder
    {
        return DB::table('client_assignments')->select('id')->whereIn('contractor_id', HomeOffice::userIds($homeOfficeId));
    }

    /**
     * The assignment for this contractor and client: the open one, otherwise the latest,
     * otherwise a new one starting today.
     */
    public static function resolve(int $contractorId, int $clientId): self
    {
        Contractor::ensureFor($contractorId);

        return static::query()
            ->where('contractor_id', $contractorId)
            ->where('client_id', $clientId)
            ->orderByRaw('end_date IS NULL DESC')
            ->orderByDesc('id')
            ->first()
            ?? static::query()->create([
                'contractor_id' => $contractorId,
                'client_id' => $clientId,
                'start_date' => now()->toDateString(),
            ]);
    }

    /**
     * Ids of the assignments of these contractors (users) and/or clients, for whereIn().
     *
     * Each can be an id, a list of ids, or a subquery that selects ids.
     *
     * @return Builder<self>
     */
    public static function idsFor(mixed $contractors = null, mixed $clients = null): Builder
    {
        return static::query()->select('id')
            ->when($contractors !== null, fn (Builder $query) => $query->whereIn('contractor_id', is_int($contractors) ? [$contractors] : $contractors))
            ->when($clients !== null, fn (Builder $query) => $query->whereIn('client_id', is_int($clients) ? [$clients] : $clients));
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * The contractor's account (contractor_id is the user's id).
     *
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Rate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class, 'assignment_id');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('end_date');
    }
}
