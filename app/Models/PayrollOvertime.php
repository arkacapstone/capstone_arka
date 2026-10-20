<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Junction: the payroll line an approved overtime was paid in (each overtime is paid once).
 */
#[Table('payroll_overtime')]
#[Fillable(['overtime_request_id', 'payroll_id'])]
class PayrollOvertime extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'overtime_request_id';
}
