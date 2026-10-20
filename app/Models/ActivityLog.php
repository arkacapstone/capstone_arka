<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'module', 'reference_table', 'reference_id', 'details'])]
class ActivityLog extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Only the entries made by users of the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('user_id'), HomeOffice::userIds($homeOfficeId));
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
