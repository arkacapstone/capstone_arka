<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SuperAdmin\EmployeeRequest;

/**
 * Admin-side contractor form (Admin flow §III). No password is set here: ARKA emails the contractor
 * a default password, which they replace on their first login.
 */
class EmployeeAccountRequest extends EmployeeRequest {}
