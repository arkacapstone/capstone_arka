<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\EmploymentType;
use App\Models\Client;
use App\Models\Rate;
use App\Models\User;
use App\Services\Settings\SystemRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 10:00:00');
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_employee_list_shows_types_counts_and_current_clients(): void
    {
        $client = Client::factory()->create(['client_name' => 'Acme']);
        $employee = User::factory()->create();
        Rate::factory()->for($employee, 'employee')->for($client)->create();
        User::factory()->partTime()->create();
        User::factory()->admin()->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Workforce/Employees')
                ->has('employees.data', 2)
                ->where('counts.total', 2)
                ->where('counts.fullTime', 1)
                ->where('counts.partTime', 1)
                ->where('employees.data', fn ($rows) => collect($rows)->firstWhere('id', $employee->id)['assignments'][0]['client']['name'] === 'Acme')
            );
    }

    public function test_employees_can_be_filtered_by_type_and_client(): void
    {
        $client = Client::factory()->create();
        $assigned = User::factory()->create(['name' => 'Assigned Person']);
        Rate::factory()->for($assigned, 'employee')->for($client)->create();
        User::factory()->create();
        User::factory()->partTime()->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.index', ['client' => $client->id]))
            ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)->where('employees.data.0.name', 'Assigned Person'));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.index', ['type' => 'part_time']))
            ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1));
    }

    public function test_a_contractor_whose_assignment_ended_shows_their_last_client(): void
    {
        $client = Client::factory()->create(['client_name' => 'Sample Client A']);
        $ended = User::factory()->create(['name' => 'Ended Person']);
        Rate::factory()->for($ended, 'employee')->for($client)->create(['effective_date' => '2026-01-01', 'end_date' => now()->subWeek()->toDateString()]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.index', ['search' => 'Ended Person']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees.data.0.assignments', 0)
                ->where('employees.data.0.endedAssignments.0.client.name', 'Sample Client A'));
    }

    public function test_only_admins_add_contractors(): void
    {
        // The Super Admin views and edits contractors; there is no way for them to add one.
        $this->assertFalse(Route::has('super-admin.workforce.employees.store'));

        $this->actingAs($this->superAdmin)
            ->post('/super-admin/workforce/employees', ['name' => 'Christian Mae', 'email' => 'christian.mae@arka.co'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseMissing(User::class, ['email' => 'christian.mae@arka.co']);
    }

    public function test_changing_a_rate_keeps_the_old_rate_in_history(): void
    {
        $employee = User::factory()->create();
        $rate = Rate::factory()->for($employee, 'employee')->create([
            'gross_pay' => 20000,
            'employment_type' => EmploymentType::PartTime,
            'effective_date' => '2026-01-01',
        ]);

        app(SystemRules::class)->update(['default_working_days' => 10, 'part_time_hours' => 4], $this->superAdmin);

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.workforce.employees.rates.update', [$employee, $rate]), [
                'gross_pay' => 22000,
                'pay_frequency' => 'semi_monthly',
                // Not editable on the rate: ignored in favour of System & Rules.
                'working_days' => 20,
                'hours_per_day' => 12,
                'effective_date' => '2026-10-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', $rate->refresh()->end_date->toDateString());
        $this->assertSame('20000.00', $rate->gross_pay);

        $current = $employee->currentRates()->sole();
        $this->assertSame('22000.00', $current->gross_pay);
        $this->assertSame($rate->client_id, $current->client_id);
        $this->assertSame('2026-10-01', $current->effective_date->toDateString());

        // Working days and hours come from System & Rules (hours by the client's Full-Time / Part-Time type).
        $this->assertSame(10, $current->working_days);
        $this->assertSame(4, $current->hours_per_day);
    }

    public function test_a_new_rate_must_start_after_the_current_one(): void
    {
        $employee = User::factory()->create();
        $rate = Rate::factory()->for($employee, 'employee')->create(['effective_date' => '2026-09-01']);

        $this->actingAs($this->superAdmin)
            ->put(route('super-admin.workforce.employees.rates.update', [$employee, $rate]), [
                'gross_pay' => 22000,
                'pay_frequency' => 'semi_monthly',
                'working_days' => 11,
                'hours_per_day' => 8,
                'effective_date' => '2026-08-15',
            ])
            ->assertSessionHasErrors('effective_date');

        $this->assertNull($rate->refresh()->end_date);
    }

    public function test_ending_an_assignment_keeps_the_rate(): void
    {
        $employee = User::factory()->create();
        $rate = Rate::factory()->for($employee, 'employee')->create(['effective_date' => '2026-01-01']);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.workforce.employees.rates.destroy', [$employee, $rate]))
            ->assertSessionHasNoErrors();

        $this->assertModelExists($rate);
        $this->assertSame('2026-09-25', $rate->refresh()->end_date->toDateString());
        $this->assertCount(0, $employee->currentRates);
    }

    public function test_a_rate_cannot_be_changed_through_another_employee(): void
    {
        $rate = Rate::factory()->create();
        $otherEmployee = User::factory()->create();

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.workforce.employees.rates.destroy', [$otherEmployee, $rate]))
            ->assertNotFound();
    }

    public function test_employee_page_shows_current_rates_and_history(): void
    {
        $employee = User::factory()->create();
        Rate::factory()->for($employee, 'employee')->create(['end_date' => '2026-06-30', 'effective_date' => '2026-01-01']);
        Rate::factory()->for($employee, 'employee')->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Workforce/EmployeeShow')
                ->where('employee.id', $employee->id)
                ->has('employee.assignments', 1)
                ->where('employee.assignments', fn ($assignments) => array_is_list($assignments->all()))
                ->has('employee.assignments.0.client.name')
                ->has('rateHistory.data', 1)
            );
    }

    public function test_admin_accounts_open_for_client_assignments_because_admins_are_also_employees(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.show', $admin))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('section', 'admins'));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.employees.show', User::factory()->superAdmin()->create()))
            ->assertNotFound();
    }
}
