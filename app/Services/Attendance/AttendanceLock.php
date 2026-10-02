<?php

namespace App\Services\Attendance;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * After the verification deadline attendance is locked for payroll (Blueprint §8, §18 step 12).
 * A locked day can no longer be corrected.
 */
class AttendanceLock
{
    public function isLocked(CarbonInterface $date): bool
    {
        return PayrollPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereIn('status', [PayrollPeriodStatus::Locked, PayrollPeriodStatus::Processed, PayrollPeriodStatus::Released])
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    public function ensureOpen(CarbonInterface $date, string $field = 'date'): void
    {
        if ($this->isLocked($date)) {
            throw ValidationException::withMessages([
                $field => 'Attendance for '.$date->format('M j, Y').' is locked for payroll and can no longer be corrected.',
            ]);
        }
    }
}
