<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Company equipment lent to a contractor. Its value is what is deducted if it is lost.
 */
#[Fillable(['device_name', 'serial_number', 'device_type', 'value', 'status'])]
class Device extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_LOST = 'lost';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
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
        return $this->hasOne(DeviceAssignment::class)->where('status', DeviceAssignment::STATUS_ACTIVE)->latestOfMany();
    }
}
