<?php

namespace App\Http\Requests\SuperAdmin;

/**
 * Contractor account details. Full-Time / Part-Time is not chosen here: it follows the contractor's
 * clients (any Full-Time client makes them a Full-Time contractor).
 */
class EmployeeRequest extends AccountRequest {}
