<?php

namespace App\Models;

use App\Models\Concerns\BelongsToContractor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A daily devotional proof upload (Blueprint §9). Compliance only — never a payroll deduction.
 */
#[Fillable(['contractor_id', 'employee_id', 'date', 'title', 'file_path', 'file_name', 'file_size', 'submitted_at'])]
class Devotional extends Model
{
    use BelongsToContractor, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * Uploaded after 12 midnight of the devotional's date is flagged Late (Blueprint §9).
     */
    public function isLate(): bool
    {
        return $this->submitted_at->greaterThanOrEqualTo(CarbonImmutable::parse($this->date->toDateString())->addDay());
    }
}
