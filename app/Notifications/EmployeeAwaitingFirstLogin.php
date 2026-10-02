<?php

namespace App\Notifications;

use App\Models\User;

/**
 * A new contractor account exists and is waiting for its first login (Admin flow §IX).
 */
class EmployeeAwaitingFirstLogin extends ArkaNotification
{
    public function __construct(private readonly User $employee) {}

    protected function title(object $notifiable): string
    {
        return 'New contractor added';
    }

    protected function message(object $notifiable): string
    {
        return "{$this->employee->name} ({$this->employee->employee_code}) was invited and is waiting to verify their email.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.employees.show', $this->employee, absolute: false);
    }

    protected function category(): string
    {
        return 'workforce';
    }
}
