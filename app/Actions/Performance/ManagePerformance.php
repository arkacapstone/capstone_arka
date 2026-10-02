<?php

namespace App\Actions\Performance;

use App\Models\KpiRecord;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardGranted;
use App\Services\ActivityLogger;

/**
 * KPI evaluations and rewards (Blueprint §3.1 Performance & Rewards). Super Admin only.
 */
class ManagePerformance
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Records (or updates) the contractor's evaluation for a payroll period.
     */
    public function evaluate(User $superAdmin, User $employee, ?int $periodId, float $score, ?string $remarks): KpiRecord
    {
        $record = KpiRecord::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'period_id' => $periodId],
            ['kpi_score' => $score, 'remarks' => $remarks, 'evaluated_by' => $superAdmin->id, 'evaluated_at' => now()],
        );

        $this->activity->log('performance', $record->wasRecentlyCreated ? 'Recorded KPI evaluation' : 'Updated KPI evaluation', $record, "{$employee->name} · {$score}");

        return $record;
    }

    public function removeEvaluation(KpiRecord $record): void
    {
        $record->rewards()->update(['kpi_id' => null]);
        $record->delete();
        $this->activity->log('performance', 'Removed KPI evaluation', null, $record->employee?->name);
    }

    /**
     * @param  array{reward_type: string, amount?: ?float, description?: ?string, kpi_id?: ?int}  $details
     */
    public function grantReward(User $superAdmin, User $employee, array $details): Reward
    {
        $reward = $employee->rewards()->create([
            ...$details,
            'awarded_by' => $superAdmin->id,
            'awarded_at' => now(),
        ]);

        $this->activity->log('performance', 'Granted reward', $reward, "{$employee->name} · {$reward->reward_type}");
        $employee->notify(new RewardGranted($reward));

        return $reward;
    }
}
