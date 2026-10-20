<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHomeOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['client_name', 'break_allowance_minutes', 'is_active'])]
class Client extends Model
{
    use BelongsToHomeOffice, HasFactory;

    /**
     * Break minutes per session for a new client. Informational only: going over never reduces pay.
     */
    public const DEFAULT_BREAK_ALLOWANCE = 60;

    protected function casts(): array
    {
        return [
            'break_allowance_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ClientAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ClientAssignment::class);
    }

    /**
     * Every rate for this client, through its assignments.
     *
     * @return HasManyThrough<Rate, ClientAssignment, $this>
     */
    public function rates(): HasManyThrough
    {
        return $this->hasManyThrough(Rate::class, ClientAssignment::class, 'client_id', 'assignment_id');
    }

    /**
     * The client with this name (ignoring case and spacing), if Admins have used it before.
     */
    public static function named(string $name): ?self
    {
        return self::query()->whereRaw('LOWER(TRIM(client_name)) = ?', [mb_strtolower(trim($name))])->orderBy('id')->first();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
