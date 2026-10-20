<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One chat message. The sender must be a participant of the conversation (composite foreign
 * key to conversation_participants), so Conversation::join() runs before a message is saved.
 */
#[Fillable(['conversation_id', 'user_id', 'body'])]
class Message extends Model
{
    protected static function booted(): void
    {
        // Only the messages of conversations in the signed-in user's home office.
        static::addGlobalScope('home_office', function (Builder $query) {
            if (($homeOfficeId = HomeOffice::scopeId()) !== null) {
                $query->whereIn(
                    $query->qualifyColumn('conversation_id'),
                    DB::table('conversations')->select('id')->whereIn('created_by', HomeOffice::userIds($homeOfficeId)),
                );
            }
        });
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
