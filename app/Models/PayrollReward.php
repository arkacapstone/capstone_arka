<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Junction: the payroll line a cash reward was paid in (each reward is paid once).
 * Non-cash rewards never get a row here.
 */
#[Table('payroll_rewards')]
#[Fillable(['reward_id', 'payroll_id'])]
class PayrollReward extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'reward_id';
}
