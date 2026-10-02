<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'employee_id', 'assigned_date', 'return_date', 'acknowledgement_signed', 'status', 'notes'])]
class DeviceAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'return_date' => 'date',
            'acknowledgement_signed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
