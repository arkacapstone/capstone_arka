<?php

namespace App\Services\Attendance;

use App\Actions\Attendance\FieldCorrected;
use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What was changed on a contractor's attendance: each applied fix with the time it replaced.
 * Shown to the Super Admin (verification results) and to the contractor (Time Tracker, Time History).
 */
class FixHistory
{
    /**
     * Applied fixes for the given days, grouped by day, oldest first.
     *
     * @param  list<string>  $dates  "Y-m-d"
     * @return array<string, list<array<string, mixed>>>
     */
    public function byDate(User $employee, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        return AttendanceCorrection::query()
            ->with('attendance.client:id,client_name')
            ->where('employee_id', $employee->id)
            ->where('status', CorrectionStatus::Approved)
            ->whereDate('date', '>=', min($dates))
            ->whereDate('date', '<=', max($dates))
            ->oldest('id')
            ->get()
            ->filter(fn (AttendanceCorrection $correction) => in_array($correction->date->toDateString(), $dates, true))
            ->groupBy(fn (AttendanceCorrection $correction) => $correction->date->toDateString())
            ->map(fn (Collection $corrections) => $corrections->map(fn (AttendanceCorrection $correction) => self::present($correction))->values()->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(AttendanceCorrection $correction): array
    {
        $before = [$correction->original_time_in, $correction->original_time_out];
        // A time left blank in the fix was kept as it was.
        $after = [$correction->requested_time_in ?? $before[0], $correction->requested_time_out ?? $before[1]];

        return [
            'id' => $correction->id,
            'attendanceId' => $correction->attendance_id,
            'date' => $correction->date->toDateString(),
            'client' => $correction->attendance?->client?->client_name,
            'field' => FieldCorrected::label($correction->field_corrected),
            'before' => self::range(...$before),
            'after' => self::range(...$after),
            'reason' => $correction->reason,
            'fixedAt' => ($correction->reviewed_at ?? $correction->created_at)->toIso8601String(),
            'by' => match (true) {
                $correction->verification_id !== null => 'Fixed during payroll verification',
                $correction->source === 'admin' => 'Corrected by an Admin',
                default => 'Correction approved by an Admin',
            },
        ];
    }

    private static function range(?string $in, ?string $out): string
    {
        return self::time($in).' – '.self::time($out);
    }

    private static function time(?string $time): string
    {
        return $time ? CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A') : '—';
    }
}
