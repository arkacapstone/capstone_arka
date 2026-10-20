<?php

namespace App\Models\Concerns;

use App\Models\Contractor;
use App\Models\HomeOffice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For the tables owned by a contractor (leave requests, cash advances, device assignments,
 * devotionals, KPI records, attendance verifications, client assignment requests).
 *
 * contractor_id is the user's id (shared primary key), so employee() is the account and
 * employee_id is another name for contractor_id. Saving makes sure the contractors row exists.
 */
trait BelongsToContractor
{
    public static function bootBelongsToContractor(): void
    {
        // Only the rows of contractors in the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('contractor_id'), HomeOffice::userIds($homeOfficeId));
            }
        });

        static::saving(function ($model) {
            if ($model->contractor_id !== null && $model->isDirty('contractor_id')) {
                Contractor::ensureFor((int) $model->contractor_id);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    protected function employeeId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['contractor_id'] ?? null,
            set: fn ($value) => ['contractor_id' => $value],
        );
    }
}
