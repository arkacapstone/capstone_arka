<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['leave_type_name', 'is_paid', 'requires_proof', 'max_days'])]
class LeaveType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_proof' => 'boolean',
            'max_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
