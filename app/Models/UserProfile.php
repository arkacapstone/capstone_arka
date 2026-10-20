<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account's personal details, kept apart from the login (users). One per user.
 */
#[Fillable(['user_id', 'birthday', 'phone_number', 'address', 'emergency_contact_name', 'emergency_contact_number', 'profile_photo_path', 'profile_completed_at'])]
class UserProfile extends Model
{
    /**
     * Columns that live here instead of on users. User reads and writes them through its profile.
     */
    public const FIELDS = ['birthday', 'phone_number', 'address', 'emergency_contact_name', 'emergency_contact_number', 'profile_photo_path', 'profile_completed_at'];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'profile_completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
