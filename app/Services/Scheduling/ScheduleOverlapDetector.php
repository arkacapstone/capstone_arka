<?php

namespace App\Services\Scheduling;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Finds other active schedules of the same contractor that share a working day and
 * overlap in time. The warning is non-blocking: overlaps can be intentional (Admin flow §IV).
 */
class ScheduleOverlapDetector
{
    /**
     * @param  array{employee_id: int, working_days: list<string>, start_time: string, end_time: string, start_date: string, end_date?: ?string}  $candidate
     * @return Collection<int, Schedule>
     */
    public function overlapping(array $candidate, ?int $ignoreId = null): Collection
    {
        $candidateStart = CarbonImmutable::parse($candidate['start_date']);
        $candidateEnd = isset($candidate['end_date']) ? CarbonImmutable::parse($candidate['end_date']) : null;

        return Schedule::query()
            ->with('client:id,client_name')
            ->where('employee_id', $candidate['employee_id'])
            ->active()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->filter(function (Schedule $schedule) use ($candidate, $candidateStart, $candidateEnd) {
                $periodsOverlap = ($candidateEnd === null || $schedule->start_date->lessThanOrEqualTo($candidateEnd))
                    && ($schedule->end_date === null || $schedule->end_date->greaterThanOrEqualTo($candidateStart));

                return $periodsOverlap
                    && array_intersect($schedule->working_days ?? [], $candidate['working_days']) !== []
                    && $this->timesOverlap($schedule->start_time, $schedule->end_time, $candidate['start_time'], $candidate['end_time']);
            })
            ->values();
    }

    private function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        [$a1, $a2] = $this->minutes($startA, $endA);
        [$b1, $b2] = $this->minutes($startB, $endB);

        return $a1 < $b2 && $b1 < $a2;
    }

    /**
     * Minutes from midnight; a shift ending at or before it starts runs into the next day.
     *
     * @return array{0: int, 1: int}
     */
    private function minutes(string $start, string $end): array
    {
        $toMinutes = fn (string $time) => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
        $from = $toMinutes($start);
        $to = $toMinutes($end);

        return [$from, $to <= $from ? $to + 1440 : $to];
    }
}
