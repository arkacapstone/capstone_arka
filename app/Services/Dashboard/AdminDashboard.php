<?php

namespace App\Services\Dashboard;

use App\Services\Dashboard\Contracts\DashboardWidget;
use App\Services\Dashboard\Widgets\Admin\ActiveSchedulesWidget;
use App\Services\Dashboard\Widgets\Admin\EmployeeOverviewWidget;
use App\Services\Dashboard\Widgets\Admin\IncompleteAttendanceWidget;
use App\Services\Dashboard\Widgets\Admin\PendingDevotionalsWidget;
use App\Services\Dashboard\Widgets\Admin\TodayAttendanceWidget;
use App\Services\Dashboard\Widgets\VerificationResultsWidget;
use Carbon\CarbonImmutable;

/**
 * Daily workforce operations at a glance (Blueprint §3.2 Dashboard, Admin flow §II).
 */
class AdminDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();

        $data = [];

        /** @var list<DashboardWidget> $widgets */
        $widgets = [
            new EmployeeOverviewWidget($today),
            new TodayAttendanceWidget($today),
            new IncompleteAttendanceWidget($today),
            new ActiveSchedulesWidget($now),
            new PendingDevotionalsWidget($today),
            // Period verification: the Admin reviews the contractors' changes and submits the period.
            new VerificationResultsWidget,
        ];

        foreach ($widgets as $widget) {
            $data[$widget->key()] = $widget->data();
        }

        $data['summary'] = $this->summary($data);

        return $data;
    }

    /**
     * The greeting's one-line status, generated from the cards so it never drifts from them.
     *
     * @param  array<string, mixed>  $data
     */
    private function summary(array $data): string
    {
        $parts = [];

        if (($data['verification']['period']['canSubmit'] ?? false) === true) {
            $parts[] = 'A verified payroll period is ready to submit to the Super Admin.';
        }

        if ($data['incomplete']['total'] > 0) {
            $count = $data['incomplete']['total'];
            $parts[] = $count === 1 ? '1 attendance record needs a correction.' : "{$count} attendance records need a correction.";
        }

        if ($data['devotionals']['pending'] > 0) {
            $count = $data['devotionals']['pending'];
            $parts[] = $count === 1 ? "1 employee hasn't submitted today's devotional." : "{$count} employees haven't submitted today's devotional.";
        }

        if ($data['onShift']['now'] > 0) {
            $parts[] = "{$data['onShift']['now']} on shift right now.";
        }

        return $parts === [] ? 'Everything is up to date.' : implode(' ', $parts);
    }
}
