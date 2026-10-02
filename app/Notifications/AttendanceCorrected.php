<?php

namespace App\Notifications;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;

/**
 * Tells a contractor their attendance was corrected or their request was reviewed.
 * Copy says what happens next, never what went wrong.
 */
class AttendanceCorrected extends ArkaNotification
{
    public function __construct(private readonly AttendanceCorrection $correction) {}

    protected function title(object $notifiable): string
    {
        return match (true) {
            $this->correction->source === 'admin' => 'Attendance updated',
            $this->correction->status === CorrectionStatus::Approved => 'Correction approved',
            default => 'Correction request closed',
        };
    }

    protected function message(object $notifiable): string
    {
        $date = $this->correction->date->format('M j, Y');

        return match (true) {
            $this->correction->source === 'admin' => "Your attendance for {$date} was corrected by an Admin. The updated record is used for payroll.",
            $this->correction->status === CorrectionStatus::Approved => "Your correction for {$date} is applied. Payroll will use the updated attendance.",
            default => trim("Your request for {$date} was closed without changes. {$this->correction->admin_remarks}"),
        };
    }

    protected function url(object $notifiable): ?string
    {
        return route('employee.attendance.index', ['month' => $this->correction->date->format('Y-m')], absolute: false);
    }

    protected function category(): string
    {
        return 'attendance';
    }
}
