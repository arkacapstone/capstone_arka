<?php

namespace Tests\Unit;

use App\Enums\PayrollPeriodStatus;
use App\Services\Payroll\PayrollPeriodResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollPeriodResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function semiMonthlyDates(): array
    {
        return [
            'first half of month' => ['2026-09-05', '2026-08-26', '2026-09-10', '2026-09-15'],
            'second half of month' => ['2026-09-18', '2026-09-11', '2026-09-25', '2026-09-30'],
            'after second cutoff' => ['2026-09-28', '2026-09-26', '2026-10-10', '2026-10-15'],
            'february releases on its last day' => ['2027-02-20', '2027-02-11', '2027-02-25', '2027-02-28'],
            'year rollover' => ['2026-12-29', '2026-12-26', '2027-01-10', '2027-01-15'],
        ];
    }

    #[DataProvider('semiMonthlyDates')]
    public function test_projects_the_semi_monthly_cycle(string $today, string $start, string $cutoff, string $release): void
    {
        $period = (new PayrollPeriodResolver)->projectSemiMonthly(CarbonImmutable::parse($today));

        $this->assertSame($start, $period->start_date->toDateString());
        $this->assertSame($cutoff, $period->cutoff_date->toDateString());
        $this->assertSame($release, $period->release_date->toDateString());
        $this->assertSame(PayrollPeriodStatus::Open, $period->status);
    }

    public function test_cutoff_day_is_the_verification_day(): void
    {
        $period = (new PayrollPeriodResolver)->projectSemiMonthly(CarbonImmutable::parse('2026-09-25'));

        $this->assertSame(PayrollPeriodStatus::Verification, $period->status);
    }
}
