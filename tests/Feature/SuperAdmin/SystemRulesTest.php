<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\LeaveType;
use App\Models\User;
use App\Services\Settings\SystemRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_every_rule_group_is_shown_with_its_current_values(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.rules'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Rules/Index')
                ->has('groups', 5)
                ->where('groups.0.key', 'payroll')
                ->where('groups.0.rules.0.key', 'default_working_days')
                ->where('groups.0.rules.0.value', 11));
    }

    public function test_saving_rules_updates_what_payroll_applies_and_is_logged(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.rules.update'), ['timer_early_start_minutes' => 15, 'late_deductions_enabled' => false])
            ->assertSessionHasNoErrors();

        $rules = app(SystemRules::class);
        $this->assertSame(15, $rules->integer('timer_early_start_minutes'));
        $this->assertFalse($rules->enabled('late_deductions_enabled'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'rules', 'action' => 'Updated system rules']);
    }

    public function test_rules_are_validated_against_their_limits(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.rules.update'), ['default_hours_per_day' => 30, 'devotional_reminder_time' => 'evening'])
            ->assertSessionHasErrors(['default_hours_per_day', 'devotional_reminder_time']);

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.rules.update'), ['first_cutoff_day' => 26])
            ->assertSessionHasErrors('second_cutoff_day');
    }

    public function test_leave_types_can_be_added_and_edited(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.rules.leave-types.store'), ['name' => 'Sick leave', 'paid' => true])
            ->assertSessionHasNoErrors();

        $type = LeaveType::query()->firstWhere('leave_type_name', 'Sick leave');
        $this->assertTrue($type->is_paid);

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.rules.leave-types.update', $type), ['name' => 'Emergency leave', 'paid' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($type->fresh()->is_paid);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.rules.leave-types.store'), ['name' => 'Emergency leave', 'paid' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_admins_cannot_change_rules(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('super-admin.rules.update'), ['default_working_days' => 20])
            ->assertForbidden();
    }
}
