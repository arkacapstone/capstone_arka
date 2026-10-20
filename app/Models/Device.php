<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHomeOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Company equipment lent to a contractor. Its value is what is deducted if it is lost.
 *
 * The stored status is the device's own condition (in service, lost, damaged, retired).
 * Whether it is assigned or available is read from its open loan, like the
 * device_current_status view, so $device->status still gives available / assigned.
 */
#[Fillable(['device_name', 'serial_number', 'device_type', 'value', 'status'])]
class Device extends Model
{
    use BelongsToHomeOffice, HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_LOST = 'lost';

    public const STATUS_DAMAGED = 'damaged';

    public const STATUS_RETIRED = 'retired';

    /**
     * The stored condition of a device that can be lent.
     */
    public const CONDITION_IN_SERVICE = 'in_service';

    /**
     * Kinds of equipment lent to contractors; anything else is "Other" with its own name.
     */
    public const TYPES = ['Laptop', 'Desktop', 'Monitor', 'Keyboard', 'Mouse', 'Headset', 'Webcam', 'Phone', 'Tablet', 'Router / Modem', 'Other'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    /**
     * Available or assigned for a device in service (from its open loan), otherwise its
     * condition. Setting available / assigned / returned stores "in service".
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (?string $condition) {
                if ($condition !== self::CONDITION_IN_SERVICE) {
                    return $condition;
                }

                $assigned = $this->relationLoaded('currentAssignment') ? $this->currentAssignment !== null : $this->currentAssignment()->exists();

                return $assigned ? self::STATUS_ASSIGNED : self::STATUS_AVAILABLE;
            },
            set: fn (string $value) => in_array($value, [self::STATUS_AVAILABLE, self::STATUS_ASSIGNED, 'returned', self::CONDITION_IN_SERVICE], true)
                ? self::CONDITION_IN_SERVICE
                : $value,
        );
    }

    /**
     * Devices with this status: available / assigned (in service, by open loan) or a condition.
     */
    #[Scope]
    protected function withStatus(Builder $query, string $status): void
    {
        match ($status) {
            self::STATUS_AVAILABLE => $query->where('status', self::CONDITION_IN_SERVICE)->whereDoesntHave('currentAssignment'),
            self::STATUS_ASSIGNED => $query->where('status', self::CONDITION_IN_SERVICE)->whereHas('currentAssignment'),
            default => $query->where('status', $status),
        };
    }

    /**
     * @return HasMany<DeviceAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    /**
     * Who has the device right now, if anyone.
     *
     * @return HasOne<DeviceAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DeviceAssignment::class)->whereNull('return_date')->latestOfMany();
    }
}
