<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One day of the week a schedule covers (day_of_week: 1 = Monday … 7 = Sunday).
 * Replaces the old JSON list of working days, so each day is its own row (1NF).
 */
#[Fillable(['schedule_id', 'day_of_week'])]
class ScheduleDay extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}
