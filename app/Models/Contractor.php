<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The anchor for every contractor-owned record (leave, cash advances, devices, devotionals,
 * KPI records, client assignments). Admins work shifts too, so they have one as well.
 *
 * Shared primary key: a contractor's id is the same number as its user's id, so a
 * contractor_id can be used wherever ARKA has the user's id.
 */
#[Fillable(['id', 'user_id'])]
class Contractor extends Model
{
    public $incrementing = false;

    protected static function booted(): void
    {
        // Only the contractors in the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('user_id'), HomeOffice::userIds($homeOfficeId));
            }
        });
    }

    /**
     * Makes sure the user has a contractors row (id = user id). Safe to call repeatedly.
     */
    public static function ensureFor(int $userId): void
    {
        static::query()->insertOrIgnore([
            'id' => $userId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ClientAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ClientAssignment::class);
    }
}
