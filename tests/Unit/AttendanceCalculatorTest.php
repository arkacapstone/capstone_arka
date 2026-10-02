<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Models\Schedule;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    private function schedule(string $start, string $end): Schedule
    {
        return new Schedule(['start_time' => $start, 'end_time' => $end, 'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']]);
    }

    public function test_on_time_full_shift_is_present(): void
    {
        $date = CarbonImmutable::parse('2026-09-21');

        $result = (new AttendanceCalculator)->calculate($this->schedule('09:00:00', '18:00:00'), $date, $date->setTime(9, 0), $date->setTime(18, 0), 60);

        $this->assertSame(AttendanceStatus::Present, $result['status']);
        $this->assertSame(8.0, $result['actual_hours']);
        $this->assertSame(0, $result['late_minutes']);
    }

    public function test_one_minute_past_the_start_is_late_with_no_grace_period(): void
    {
        $date = CarbonImmutable::parse('2026-09-21');

        $result = (new AttendanceCalculator)->calculate($this->schedule('09:00:00', '18:00:00'), $date, $date->setTime(9, 1), $date->setTime(18, 0));

        $this->assertSame(AttendanceStatus::Late, $result['status']);
        $this->assertSame(1, $result['late_minutes']);
    }

    public function test_leaving_early_is_undertime(): void
    {
        $date = CarbonImmutable::parse('2026-09-21');

        $result = (new AttendanceCalculator)->calculate($this->schedule('09:00:00', '18:00:00'), $date, $date->setTime(9, 0), $date->setTime(17, 30));

        $this->assertSame(AttendanceStatus::Undertime, $result['status']);
        $this->assertSame(30, $result['undertime_minutes']);
    }

    public function test_graveyard_shift_is_one_work_period_across_midnight(): void
    {
        $date = CarbonImmutable::parse('2026-09-21');

        $result = (new AttendanceCalculator)->calculate($this->schedule('22:00:00', '06:00:00'), $date, $date->setTime(22, 0), $date->addDay()->setTime(6, 0));

        $this->assertSame(AttendanceStatus::Present, $result['status']);
        $this->assertSame(8.0, $result['actual_hours']);
        $this->assertSame(0, $result['undertime_minutes']);
    }

    public function test_missing_clock_out_is_incomplete_and_no_clock_in_is_absent(): void
    {
        $date = CarbonImmutable::parse('2026-09-21');
        $calculator = new AttendanceCalculator;

        $this->assertSame(AttendanceStatus::Incomplete, $calculator->calculate(null, $date, $date->setTime(9, 0), null)['status']);
        $this->assertSame(AttendanceStatus::Absent, $calculator->calculate(null, $date, null, null)['status']);
    }
}
