<?php

namespace App\Models;

use App\Models\Concerns\BelongsToContractor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One loan of a device to a contractor. It is open while return_date is empty; end_reason
 * says how it ended (returned, lost or damaged). The status is read from those two.
 */
#[Fillable(['device_id', 'contractor_id', 'employee_id', 'assigned_date', 'return_date', 'acknowledgement_signed', 'end_reason', 'status', 'notes'])]
class DeviceAssignment extends Model
{
    use BelongsToContractor, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_LOST = 'lost';

    public const STATUS_DAMAGED = 'damaged';

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'return_date' => 'date',
            'acknowledgement_signed' => 'boolean',
        ];
    }

    /**
     * Active while open, otherwise how it ended. Setting it ends the loan (today unless a
     * return date is given) or reopens it.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->return_date === null ? self::STATUS_ACTIVE : ($this->end_reason ?? self::STATUS_RETURNED),
            set: fn (string $value) => $value === self::STATUS_ACTIVE
                ? ['end_reason' => null, 'return_date' => null]
                : ['end_reason' => $value, 'return_date' => $this->attributes['return_date'] ?? now()->toDateString()],
        );
    }

    /**
     * Loans that have not ended.
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('return_date'));
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * The deduction charged for this device when it was lost.
     *
     * @return HasOne<DeviceDeduction, $this>
     */
    public function deduction(): HasOne
    {
        return $this->hasOne(DeviceDeduction::class);
    }
}
