<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\KpiRecord;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Notifications\RewardGranted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_evaluating_the_same_employee_and_period_again_updates_the_evaluation(): void
    {
        $employee = User::factory()->create();
        $period = PayrollPeriod::factory()->create();

        foreach ([82, 91] as $score) {
            $this->actingAs($this->superAdmin)
                ->post(route('super-admin.performance.evaluate'), ['employee_id' => $employee->id, 'period_id' => $period->id, 'kpi_score' => $score, 'remarks' => 'Solid'])
                ->assertRedirect(route('super-admin.performance'));
        }

        $this->assertDatabaseCount('kpi_records', 1);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.performance'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Performance/Index')
                ->where('kpis.0.score', 91)
                ->where('kpis.0.rating', 'Excellent')
                ->where('summary.evaluations', 1));
    }

    public function test_choosing_an_employee_suggests_a_score_from_their_records(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.performance', ['employee' => $employee->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('suggestion.employeeId', $employee->id)
                ->has('suggestion.suggested'));
    }

    public function test_scores_outside_zero_to_one_hundred_are_refused(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.performance.evaluate'), ['employee_id' => User::factory()->create()->id, 'kpi_score' => 120])
            ->assertSessionHasErrors('kpi_score');
    }

    public function test_granting_a_reward_notifies_the_employee_and_appears_in_history(): void
    {
        Notification::fake();
        $employee = User::factory()->create();
        $kpi = KpiRecord::create(['employee_id' => $employee->id, 'kpi_score' => 95, 'evaluated_at' => now()]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.performance.reward'), ['employee_id' => $employee->id, 'reward_type' => 'Bonus', 'amount' => 1500, 'kpi_id' => $kpi->id])
            ->assertRedirect(route('super-admin.performance', ['tab' => 'rewards']));

        Notification::assertSentTo($employee, RewardGranted::class);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.performance', ['tab' => 'rewards']))
            ->assertInertia(fn (Assert $page) => $page->has('rewards', 1)->where('rewards.0.type', 'Bonus')->where('summary.incentivesThisMonth', 1500));
    }

    public function test_removing_an_evaluation_keeps_its_rewards(): void
    {
        $employee = User::factory()->create();
        $kpi = KpiRecord::create(['employee_id' => $employee->id, 'kpi_score' => 75, 'evaluated_at' => now()]);
        $reward = $employee->rewards()->create(['kpi_id' => $kpi->id, 'reward_type' => 'Recognition', 'awarded_at' => now()]);

        $this->actingAs($this->superAdmin)->delete(route('super-admin.performance.evaluations.destroy', $kpi))->assertSessionHasNoErrors();

        $this->assertModelMissing($kpi);
        $this->assertNull($reward->fresh()->kpi_id);
    }
}
