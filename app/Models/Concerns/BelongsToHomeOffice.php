<?php

namespace App\Models\Concerns;

use App\Models\HomeOffice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For the "root" tables that carry home_office_id (users, clients, leave_types,
 * payroll_periods, devices): a new row is put in the current home office unless one is
 * given, and queries only see the rows of the signed-in user's home office (global scope).
 */
trait BelongsToHomeOffice
{
    public static function bootBelongsToHomeOffice(): void
    {
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->where($query->qualifyColumn('home_office_id'), $homeOfficeId);
            }
        });

        static::creating(function ($model) {
            $model->home_office_id ??= HomeOffice::currentId();
        });
    }

    /**
     * @return BelongsTo<HomeOffice, $this>
     */
    public function homeOffice(): BelongsTo
    {
        return $this->belongsTo(HomeOffice::class);
    }
}
