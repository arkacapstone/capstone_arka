<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Released = 'released';
}
