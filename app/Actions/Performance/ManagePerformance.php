<?php

namespace App\Actions\Performance;

use App\Models\KpiRecord;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardGranted;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

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
            ['contractor_id' => $employee->id, 'period_id' => $periodId],
            ['kpi_score' => $score, 'remarks' => $remarks, 'evaluated_by' => $superAdmin->id, 'evaluated_at' => now()],
        );

        $this->activity->log('performance', $record->wasRecentlyCreated ? 'Recorded KPI evaluation' : 'Updated KPI evaluation', $record, "{$employee->name} · {$score}");

        return $record;
    }

    public function removeEvaluation(KpiRecord $record): void
    {
        // Every reward comes from a KPI record, so an evaluation with rewards cannot be removed.
        if ($record->rewards()->exists()) {
            throw ValidationException::withMessages(['kpi' => 'This evaluation has rewards, and every reward comes from an evaluation, so it cannot be removed.']);
        }

        $record->delete();
        $this->activity->log('performance', 'Removed KPI evaluation', null, $record->employee?->name);
    }

    /**
     * Every reward comes from a KPI record: the one given, otherwise the contractor's latest.
     * With an amount it is a cash reward (paid in payroll), without one a non-cash reward.
     * The kind (Bonus, Recognition, …) is written at the start of the description.
     *
     * @param  array{reward_type: string, amount?: ?float, description?: ?string, kpi_id?: ?int}  $details
     */
    public function grantReward(User $superAdmin, User $employee, array $details): Reward
    {
        $kpi = KpiRecord::query()
            ->where('contractor_id', $employee->id)
            ->when($details['kpi_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->latest('evaluated_at')
            ->latest('id')
            ->first()
            ?? throw ValidationException::withMessages(['kpi_id' => "Record a KPI evaluation for {$employee->name} first: every reward comes from an evaluation."]);

        $amount = (float) ($details['amount'] ?? 0);
        $note = trim((string) ($details['description'] ?? ''));

        $reward = Reward::query()->create([
            'kpi_id' => $kpi->id,
            'reward_type' => $amount > 0 ? Reward::CASH : Reward::NON_CASH,
            'amount' => $amount > 0 ? $amount : null,
            'description' => $note === '' ? $details['reward_type'] : "{$details['reward_type']} — {$note}",
            'awarded_by' => $superAdmin->id,
            'awarded_at' => now(),
        ]);

        $this->activity->log('performance', 'Granted reward', $reward, "{$employee->name} · {$reward->description}");
        $employee->notify(new RewardGranted($reward));

        return $reward;
    }
}
