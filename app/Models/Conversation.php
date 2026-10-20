<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A chat. For now ARKA uses one kind: each home office's community chat, where everyone in the
 * home office (Super Admin, Admins, Contractors) talks together. The tables also allow direct
 * and group chats later.
 *
 * There is no home_office_id column: a conversation belongs to the home office of the user who
 * created it (created_by), and the global scope uses that.
 */
#[Fillable(['type', 'name', 'created_by'])]
class Conversation extends Model
{
    public const COMMUNITY = 'community';

    protected static function booted(): void
    {
        // Only the conversations of the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn($query->qualifyColumn('created_by'), HomeOffice::userIds($homeOfficeId));
            }
        });
    }

    /**
     * The signed-in user's home office community chat, made the first time someone opens it.
     */
    public static function communityFor(User $user): self
    {
        return static::query()->where('type', self::COMMUNITY)->oldest('id')->first()
            ?? static::query()->create(['type' => self::COMMUNITY, 'created_by' => $user->id]);
    }

    /**
     * Adds the user to this conversation if they are not in it yet. Safe to call repeatedly.
     */
    public function join(User $user): void
    {
        DB::table('conversation_participants')->insertOrIgnore([
            'conversation_id' => $this->id,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
    }

    /**
     * Marks everything in this conversation as read by the user (the basis of the unread count).
     */
    public function markReadBy(User $user): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $this->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['joined_at', 'left_at', 'last_read_at']);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
