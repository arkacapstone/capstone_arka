<?php

namespace Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * For factories of models that point to client_assignments (rates, schedules, time logs,
 * attendance, corrections, payroll). Their employee() and client() relations go through the
 * assignment, so ->for($user, 'employee') / ->for($client) set employee_id / client_id instead,
 * and the model picks the assignment when it is saved.
 */
trait ForAssignment
{
    public function for($factory, $relationship = null)
    {
        $relationship ??= Str::camel(class_basename($factory instanceof Factory ? $factory->modelName() : $factory));

        $key = match ($relationship) {
            'employee', 'user' => 'employee_id',
            'client' => 'client_id',
            default => null,
        };

        if ($key === null) {
            return parent::for($factory, $relationship);
        }

        return $this->state([$key => $factory instanceof Model ? $factory->getKey() : $factory]);
    }
}
