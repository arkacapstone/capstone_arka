<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_name', 'client_code', 'break_allowance_minutes', 'is_active'])]
class Client extends Model
{
    use HasFactory;

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
     * @return HasMany<Rate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    /**
     * The client with this name (ignoring case and spacing), if Admins have used it before.
     */
    public static function named(string $name): ?self
    {
        return self::query()->whereRaw('LOWER(TRIM(client_name)) = ?', [mb_strtolower(trim($name))])->orderBy('id')->first();
    }

    /**
     * Next free code for a new client: CL-0001, CL-0002, ...
     */
    public static function nextCode(): string
    {
        $number = (int) self::query()->max('id') + 1;

        do {
            $code = 'CL-'.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (self::query()->where('client_code', $code)->exists());

        return $code;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
