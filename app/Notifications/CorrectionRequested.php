<?php

namespace App\Notifications;

use App\Models\AttendanceCorrection;

/**
 * A contractor asked for an attendance correction; Admins review it (Admin flow §V).
 */
class CorrectionRequested extends ArkaNotification
{
    public function __construct(private readonly AttendanceCorrection $correction) {}

    protected function title(object $notifiable): string
    {
        return 'Correction request to review';
    }

    protected function message(object $notifiable): string
    {
        $this->correction->loadMissing('employee:id,name');

        return "{$this->correction->employee->name} asked to correct {$this->correction->date->format('M j, Y')}. Approve or close it in Attendance.";
    }

    protected function url(object $notifiable): ?string
    {
        return route('admin.attendance.index', ['tab' => 'requests'], absolute: false);
    }

    protected function category(): string
    {
        return 'attendance';
    }
}
