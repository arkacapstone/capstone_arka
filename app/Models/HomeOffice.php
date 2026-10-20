<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A home office (workforce company) using ARKA. Its name is the organization name shown
 * across ARKA. Each home office only sees its own records: see scopeId() and the global
 * scopes in BelongsToHomeOffice, BelongsToContractor and BelongsToAssignment.
 */
#[Fillable(['name', 'address'])]
class HomeOffice extends Model
{
    /**
     * Name of the home office made when none exists yet (e.g. a fresh test database).
     */
    public const DEFAULT_NAME = 'Makarius Virtual Solutions';

    /**
     * The home office chosen by within(), for work done without a signed-in user
     * (scheduled commands).
     */
    private static ?int $forcedId = null;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The home office whose records queries are limited to: the one chosen by within(),
     * otherwise the signed-in user's. Null means no limit (e.g. before login, or a command
     * that is not running within() a home office).
     *
     * Auth::hasUser() is used instead of Auth::user() so loading the signed-in user itself
     * (a users query) never asks for the user again.
     */
    public static function scopeId(): ?int
    {
        return self::$forcedId ?? (Auth::hasUser() ? Auth::user()->home_office_id : null);
    }

    /**
     * Subquery of the user ids in this home office, for the global scopes of the tables
     * that reach the home office through a user (contractor_id, user_id).
     */
    public static function userIds(int $homeOfficeId): QueryBuilder
    {
        return DB::table('users')->select('id')->where('home_office_id', $homeOfficeId);
    }

    /**
     * The home office new records belong to: the scoped one, otherwise the first one
     * (made on first use).
     */
    public static function currentId(): int
    {
        return self::scopeId()
            ?? static::query()->orderBy('id')->value('id')
            ?? static::query()->create(['name' => self::DEFAULT_NAME])->id;
    }

    /**
     * Runs the callback as if inside this home office: queries see only its records and
     * settings are read from it. Used by scheduled commands, which have no signed-in user.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public static function within(int $homeOfficeId, callable $callback): mixed
    {
        $previous = self::$forcedId;
        self::$forcedId = $homeOfficeId;

        try {
            return $callback();
        } finally {
            self::$forcedId = $previous;
        }
    }

    /**
     * Runs the callback once for every home office (see within()).
     *
     * @param  callable(self): void  $callback
     */
    public static function each(callable $callback): void
    {
        foreach (static::query()->orderBy('id')->get() as $homeOffice) {
            self::within($homeOffice->id, fn () => $callback($homeOffice));
        }
    }
}
