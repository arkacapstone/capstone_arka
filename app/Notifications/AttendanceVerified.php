<?php

namespace App\Notifications;

use App\Models\AttendanceVerification;
use App\Models\User;

/**
 * A contractor submitted their attendance as verified for payroll (Blueprint §14). The Super Admin
 * and Admins see who confirmed, and how many days they fixed first.
 */
class AttendanceVerified extends ArkaNotification
{
    public function __construct(private readonly AttendanceVerification $verification) {}

    protected function title(object $notifiable): string
    {
        return 'Attendance verified';
    }

    protected function message(object $notifiable): string
    {
        $this->verification->loadMissing('employee:id,name,employee_code', 'period:id,period_name');
        $fixes = $this->verification->corrections()->count();
        $fixed = $fixes === 0 ? 'No days fixed.' : ($fixes === 1 ? '1 fix made before submitting.' : "{$fixes} fixes made before submitting.");

        return "{$this->verification->employee->name} ({$this->verification->employee->employee_code}) confirmed their attendance for {$this->verification->period->period_name}. {$fixed}";
    }

    protected function url(object $notifiable): ?string
    {
        return $notifiable instanceof User && $notifiable->isSuperAdmin()
            ? route('super-admin.payroll', ['tab' => 'verification'], absolute: false)
            : route('admin.attendance.index', absolute: false);
    }

    protected function category(): string
    {
        return 'attendance';
    }
}
