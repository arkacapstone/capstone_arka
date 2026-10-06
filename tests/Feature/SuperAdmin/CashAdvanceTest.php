<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\CashAdvanceStatus;
use App\Models\CashAdvance;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\User;
use App\Notifications\CashAdvanceDecided;
use App\Notifications\CashAdvanceRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    /**
     * A contractor in the Sep 26 – Oct 10 pay period (not created yet), with ₱20,000 gross pay for it.
     */
    private function contractorBeforePayday(): User
    {
        $this->travelTo('2026-09-26 10:00:00');
        PayrollPeriod::factory()->create();
        $employee = User::factory()->create();
        Rate::factory()->for($employee, 'employee')->create(['effective_date' => '2026-01-01']);

        return $employee;
    }

    public function test_an_employee_requests_a_cash_advance_and_the_super_admin_is_notified(): void
    {
        Notification::fake();
        $employee = $this->contractorBeforePayday();

        $this->actingAs($employee)
            ->post(route('employee.cash-advances.store'), ['amount' => 2500, 'reason' => 'Laptop repair'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_advances', ['employee_id' => $employee->id, 'amount' => 2500, 'status' => 'pending']);
        Notification::assertSentTo($this->superAdmin, CashAdvanceRequested::class);

        $this->actingAs($employee)
            ->get(route('employee.cash-advances.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Employee/CashAdvances')->has('advances', 1)->where('advances.0.canCancel', true));

        $this->actingAs($employee)
            ->get(route('employee.payslips.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('cashAdvances'));
    }

    public function test_requests_above_the_gross_pay_are_refused(): void
    {
        // Up to the ₱20,000 gross pay for the period.
        $this->actingAs($this->contractorBeforePayday())
            ->post(route('employee.cash-advances.store'), ['amount' => 20000.01, 'reason' => 'Too much'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('cash_advances', 0);
    }

    public function test_an_employee_can_withdraw_only_their_own_pending_request(): void
    {
        $advance = CashAdvance::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('employee.cash-advances.cancel', $advance))->assertNotFound();

        $this->actingAs($advance->employee)->post(route('employee.cash-advances.cancel', $advance))->assertSessionHasNoErrors();
        $this->assertSame(CashAdvanceStatus::Cancelled, $advance->fresh()->status);
    }

    public function test_the_super_admin_lists_pending_requests_and_approves_one(): void
    {
        Notification::fake();
        $advance = CashAdvance::factory()->create(['amount' => 4000, 'remaining_balance' => 4000]);

        // Cash advances are a tab of Requests & Approvals; the old address leads there.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.cash-advances'))
            ->assertRedirect(route('super-admin.requests', ['type' => 'cash-advances']));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.requests', ['type' => 'cash-advances']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Requests/Index')
                ->has('cashAdvances.advances', 1)
                ->where('cashAdvances.summary.pending', 1)
                ->where('pendingCashAdvances', 1));

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.cash-advances.approve', $advance))
            ->assertSessionHasNoErrors();

        $advance->refresh();
        $this->assertSame(CashAdvanceStatus::Approved, $advance->status);
        $this->assertSame(today()->toDateString(), $advance->released_date->toDateString());
        Notification::assertSentTo($advance->employee, CashAdvanceDecided::class);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.requests', ['type' => 'cash-advances', 'tab' => 'active']))
            ->assertInertia(fn (Assert $page) => $page->has('cashAdvances.advances', 1)->where('cashAdvances.summary.outstanding', 4000));
    }

    public function test_the_super_admin_rejects_a_request(): void
    {
        $advance = CashAdvance::factory()->create();

        $this->actingAs($this->superAdmin)->post(route('super-admin.cash-advances.reject', $advance))->assertSessionHasNoErrors();

        $this->assertSame(CashAdvanceStatus::Rejected, $advance->fresh()->status);
    }

    public function test_the_super_admin_has_no_form_to_file_or_repay_cash_advances(): void
    {
        $this->assertFalse(Route::has('super-admin.cash-advances.store'));
        $this->assertFalse(Route::has('super-admin.cash-advances.repay'));

        $this->actingAs($this->superAdmin)
            ->post('/super-admin/cash-advances', ['employee_id' => User::factory()->create()->id, 'amount' => 1500, 'reason' => 'x'])
            ->assertStatus(405);
    }

    public function test_admins_cannot_decide_cash_advances(): void
    {
        $advance = CashAdvance::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('super-admin.cash-advances.approve', $advance))
            ->assertForbidden();
    }
}
