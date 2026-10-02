<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Performance\ManagePerformance;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\KpiRecord;
use App\Models\PayrollPeriod;
use App\Models\Reward;
use App\Models\User;
use App\Services\Performance\KpiMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Performance & Rewards (Blueprint §3.1 module 7): KPI records, evaluation,
 * results, rewards/incentives and reward history.
 */
class PerformanceController extends Controller
{
    public function index(Request $request, KpiMetrics $metrics): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:kpi,rewards'],
            'employee' => ['nullable', 'integer'],
            'period' => ['nullable', 'integer'],
        ]);

        $periods = PayrollPeriod::query()->orderByDesc('start_date')->limit(24)->get();

        // The evaluation form asks for suggested metrics for one contractor and period.
        $suggestion = null;
        if (isset($filters['employee']) && $employee = User::query()->workforce()->find($filters['employee'])) {
            $period = isset($filters['period']) ? $periods->firstWhere('id', (int) $filters['period']) : null;
            $from = $period?->start_date ?? CarbonImmutable::today()->startOfMonth();
            $to = $period?->end_date ?? CarbonImmutable::today();
            $suggestion = ['employeeId' => $employee->id, 'periodId' => $period?->id, 'from' => $from->toDateString(), 'to' => $to->toDateString(), ...$metrics->for($employee, $from, $to)];
        }

        $kpis = KpiRecord::query()
            ->with(['employee:id,name,employee_code', 'period:id,period_name', 'evaluator:id,name'])
            ->withCount('rewards')
            ->latest('evaluated_at')
            ->limit(100)
            ->get();

        $rewards = Reward::query()
            ->with(['employee:id,name,employee_code', 'awarder:id,name', 'kpi.period:id,period_name'])
            ->latest('awarded_at')
            ->limit(100)
            ->get();

        return Inertia::render('SuperAdmin/Performance/Index', [
            'tab' => $filters['tab'] ?? 'kpi',
            'kpis' => $kpis->map(fn (KpiRecord $record) => [
                'id' => $record->id,
                'employee' => ['id' => $record->employee->id, 'name' => $record->employee->name, 'code' => $record->employee->employee_code],
                'period' => $record->period?->period_name ?? 'General',
                'periodId' => $record->period_id,
                'score' => (float) $record->kpi_score,
                'rating' => $record->rating(),
                'remarks' => $record->remarks,
                'evaluator' => $record->evaluator?->name,
                'evaluatedAt' => $record->evaluated_at?->toIso8601String(),
                'rewards' => $record->rewards_count,
            ])->all(),
            'rewards' => $rewards->map(fn (Reward $reward) => [
                'id' => $reward->id,
                'employee' => ['name' => $reward->employee->name, 'code' => $reward->employee->employee_code],
                'type' => $reward->reward_type,
                'amount' => $reward->amount !== null ? (float) $reward->amount : null,
                'description' => $reward->description,
                'kpi' => $reward->kpi ? ($reward->kpi->period?->period_name ?? 'General').' · '.$reward->kpi->kpi_score : null,
                'awarder' => $reward->awarder?->name,
                'awardedAt' => $reward->awarded_at?->toIso8601String(),
            ])->all(),
            'summary' => [
                'evaluations' => $kpis->count(),
                'averageScore' => $kpis->isEmpty() ? null : round($kpis->avg(fn (KpiRecord $record) => (float) $record->kpi_score), 1),
                'rewardsThisMonth' => Reward::query()->where('awarded_at', '>=', CarbonImmutable::today()->startOfMonth())->count(),
                'incentivesThisMonth' => round((float) Reward::query()->where('awarded_at', '>=', CarbonImmutable::today()->startOfMonth())->sum('amount'), 2),
            ],
            'suggestion' => $suggestion,
            'employees' => User::query()->workforce()->active()->orderBy('name')->get(['id', 'name', 'employee_code'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => "{$user->name} ({$user->employee_code})"])->all(),
            'periods' => $periods->map(fn (PayrollPeriod $period) => ['value' => $period->id, 'label' => $period->period_name])->all(),
            'rewardTypes' => array_map(fn (string $type) => ['value' => $type, 'label' => $type], Reward::TYPES),
        ]);
    }

    public function evaluate(Request $request, ManagePerformance $manage): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues())],
            'period_id' => ['nullable', 'integer', Rule::exists(PayrollPeriod::class, 'id')],
            'kpi_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $manage->evaluate($request->user(), User::findOrFail($validated['employee_id']), $validated['period_id'] ?? null, (float) $validated['kpi_score'], $validated['remarks'] ?? null);

        return to_route('super-admin.performance')->with('success', 'KPI evaluation saved.');
    }

    public function destroyEvaluation(KpiRecord $kpi, ManagePerformance $manage): RedirectResponse
    {
        $manage->removeEvaluation($kpi);

        return back()->with('success', 'KPI evaluation removed.');
    }

    public function reward(Request $request, ManagePerformance $manage): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')->whereIn('role', UserRole::workforceValues())],
            'reward_type' => ['required', Rule::in(Reward::TYPES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kpi_id' => ['nullable', 'integer', Rule::exists(KpiRecord::class, 'id')],
        ]);

        $employee = User::findOrFail($validated['employee_id']);
        unset($validated['employee_id']);

        $manage->grantReward($request->user(), $employee, $validated);

        return to_route('super-admin.performance', ['tab' => 'rewards'])->with('success', 'Reward recorded. The contractor has been notified.');
    }
}
