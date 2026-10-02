<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['device_name', 'serial_number', 'device_type', 'status'])]
class Device extends Model
{
    use HasFactory;

    /**
     * @return HasMany<DeviceAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }
}
