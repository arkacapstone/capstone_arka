<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Settings\SystemRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Each home office only sees its own records. Home office A (Makarius) has a contractor,
 * a client, attendance and a payroll period; home office B (Harbor) registers from the
 * login page and must see none of them.
 */
class HomeOfficeIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdminA;

    private User $contractorA;

    private Client $clientA;

    private PayrollPeriod $periodA;

    private User $superAdminB;

    protected function setUp(): void
    {
        parent::setUp();

        // Home office A: made before anyone signs in, so it is the first home office.
        $this->superAdminA = User::factory()->superAdmin()->create();
        $this->contractorA = User::factory()->create(['name' => 'Ana Contractor']);
        $this->clientA = Client::factory()->create(['client_name' => 'Client of A']);
        $this->periodA = PayrollPeriod::factory()->create();
        Attendance::factory()->create(['employee_id' => $this->contractorA->id, 'client_id' => $this->clientA->id]);

        // Home office B registers.
        $this->post('/register', [
            'home_office_name' => 'Harbor Staffing',
            'name' => 'Hana Reyes',
            'email' => 'hana@harbor.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->superAdminB = User::query()->withoutGlobalScopes()->where('email', 'hana@harbor.test')->sole();
        $this->assertNotSame($this->superAdminA->home_office_id, $this->superAdminB->home_office_id);
    }

    public function test_lists_show_only_the_signed_in_home_office_records(): void
    {
        $this->actingAs($this->superAdminB)
            ->get(route('super-admin.workforce.clients.index'))
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 0));

        $this->actingAs($this->superAdminB)
            ->get(route('super-admin.workforce.employees.index'))
            ->assertInertia(fn (Assert $page) => $page->has('employees.data', 0));

        // Home office A still sees its own.
        $this->actingAs($this->superAdminA)
            ->get(route('super-admin.workforce.clients.index'))
            ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.name', 'Client of A'));
    }

    public function test_another_home_office_record_is_not_found_even_by_its_link(): void
    {
        $this->actingAs($this->superAdminB)
            ->get(route('super-admin.workforce.employees.show', $this->contractorA))
            ->assertNotFound();

        $this->actingAs($this->superAdminB)
            ->get(route('super-admin.payroll.show', $this->periodA))
            ->assertNotFound();

        $this->actingAs($this->superAdminA)
            ->get(route('super-admin.workforce.employees.show', $this->contractorA))
            ->assertOk();
    }

    public function test_records_reached_through_a_contractor_are_hidden_too(): void
    {
        $this->actingAs($this->superAdminB);
        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, User::query()->where('id', $this->contractorA->id)->count());

        $this->actingAs($this->superAdminA);
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_another_home_office_contractor_cannot_be_picked_in_a_form(): void
    {
        $this->actingAs($this->superAdminB)
            ->post(route('super-admin.performance.evaluate'), ['employee_id' => $this->contractorA->id])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_each_home_office_has_its_own_rules_and_organization_name(): void
    {
        $this->actingAs($this->superAdminB)
            ->put(route('super-admin.rules.update'), ['timer_early_start_minutes' => 15])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->superAdminB)
            ->get(route('super-admin.rules'))
            ->assertInertia(fn (Assert $page) => $page->where('groups.4.rules.0.value', 'Harbor Staffing'));

        $this->actingAs($this->superAdminB);
        $this->assertSame(15, app(SystemRules::class)->integer('timer_early_start_minutes'));

        $this->actingAs($this->superAdminA);
        $this->assertNotSame(15, app(SystemRules::class)->integer('timer_early_start_minutes'));
    }
}
