<?php

namespace Tests\Feature\Admin;

use App\Enums\PayFrequency;
use App\Enums\PayrollPeriodStatus;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\PayrollPeriod;
use App\Models\Rate;
use App\Models\User;
use App\Notifications\VerificationReminder;
use App\Notifications\VerifiedPeriodSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeriodVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $submitted;

    private User $waiting;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26 10:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->superAdmin = User::factory()->superAdmin()->create();
        $client = Client::factory()->create();

        $this->submitted = User::factory()->create(['name' => 'Ana Submitted']);
        $this->waiting = User::factory()->create(['name' => 'Zed Waiting']);

        foreach ([$this->submitted, $this->waiting] as $contractor) {
            Rate::factory()->for($contractor, 'employee')->for($client)->create(['pay_frequency' => PayFrequency::SemiMonthly, 'effective_date' => '2026-01-01']);
        }

        // Sep 11 – Sep 25, opened for verification this morning: fixing is open until midnight.
        $this->period = PayrollPeriod::factory()->create(['status' => PayrollPeriodStatus::Verification, 'verification_opened_at' => now()->setTime(8, 0)]);

        $day = Attendance::factory()->for($this->submitted, 'employee')->for($client)->create([
            'date' => '2026-09-15',
            'time_in' => '2026-09-15 09:00:00',
            'time_out' => null,
        ]);

        $this->actingAs($this->submitted)->post(route('employee.attendance.verification.fix', $this->period), [
            'attendance_id' => $day->id,
            'time_out' => '18:00',
            'reason' => 'Forgot to stop my timer.',
        ]);
        $this->post(route('employee.attendance.verification.submit', $this->period))->assertSessionHasNoErrors();
    }

    public function test_the_admin_reviews_every_change_in_period_verification(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.verification.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Verification/Index')
                ->where('verification.period.id', $this->period->id)
                ->where('verification.counts.verified', 1)
                ->where('verification.counts.waiting', 1)
                ->where('verification.counts.fixes', 1)
                ->where('verification.contractors.0.id', $this->submitted->id)
                ->where('verification.contractors.0.fixes.0.before', '9:00 AM – —')
                ->where('verification.contractors.0.fixes.0.after', '9:00 AM – 6:00 PM')
                ->where('verification.contractors.1.id', $this->waiting->id)
                ->where('verification.period.canRemind', true)
                ->where('verification.period.canSubmit', false));

        // The dashboard links to the module with a summary.
        $this->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('verification.counts.waiting', 1)
                ->where('navigation', fn ($items) => collect($items)->contains('key', 'verification')));
    }

    public function test_a_reminder_tells_contractors_who_have_not_submitted_how_long_they_have_left(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.verification.remind', $this->period))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Reminder sent to 1 contractor.');

        Notification::assertSentTo($this->waiting, VerificationReminder::class, function (VerificationReminder $notification) {
            $message = $notification->toArray($this->waiting)['message'];

            return str_contains($message, "It's already the cut-off")
                && str_contains($message, 'submit your attendance as verified now')
                && str_contains($message, 'You have 13 hours 59 minutes left to make changes');
        });
        Notification::assertNotSentTo($this->submitted, VerificationReminder::class);

        // The page shows that the reminder went out.
        $this->assertSame(1, $this->period->refresh()->last_reminded_count);
        $this->get(route('admin.verification.index'))
            ->assertInertia(fn (Assert $page) => $page->where('verification.period.lastRemindedCount', 1)->whereNot('verification.period.lastRemindedAt', null));
    }

    public function test_an_admin_who_is_also_paid_in_the_period_is_reminded_too(): void
    {
        Notification::fake();
        Rate::factory()->for($this->admin, 'employee')->create(['pay_frequency' => PayFrequency::SemiMonthly, 'effective_date' => '2026-01-01']);

        $this->actingAs($this->admin)
            ->post(route('admin.verification.remind', $this->period))
            ->assertSessionHas('success', 'Reminder sent to 2 contractors.');

        Notification::assertSentTo($this->admin, VerificationReminder::class);
    }

    public function test_the_admin_submits_last_and_only_then_can_the_super_admin_process_payroll(): void
    {
        Notification::fake();

        // The Super Admin no longer reviews: Process payroll waits for the Admin.
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.advance', $this->period))
            ->assertSessionHasErrors('status');

        // Someone has not submitted and can still fix today, so the Admin cannot submit yet.
        $this->actingAs($this->admin)
            ->post(route('admin.verification.submit', $this->period))
            ->assertSessionHasErrors('period');

        // After midnight fixing has closed; the Admin submits the verified period.
        $this->travelTo('2026-09-27 08:00:00');
        $this->actingAs($this->admin)
            ->post(route('admin.verification.submit', $this->period))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->admin->id, $this->period->refresh()->admin_submitted_by);
        Notification::assertSentTo($this->superAdmin, VerifiedPeriodSubmitted::class);

        // Contractors can no longer submit, and the reminder is gone.
        $this->actingAs($this->waiting)
            ->post(route('employee.attendance.verification.submit', $this->period))
            ->assertSessionHasErrors('time_in');
        $this->actingAs($this->admin)
            ->post(route('admin.verification.remind', $this->period))
            ->assertSessionHasErrors('period');

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.advance', $this->period))
            ->assertSessionHasNoErrors();

        $this->assertSame(PayrollPeriodStatus::Locked, $this->period->refresh()->status);
    }

    public function test_unlocking_attendance_asks_the_admin_to_submit_again(): void
    {
        $this->period->update(['status' => PayrollPeriodStatus::Locked, 'admin_submitted_at' => now(), 'admin_submitted_by' => $this->admin->id]);

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.payroll.revert', $this->period))
            ->assertSessionHasNoErrors();

        $this->assertSame(PayrollPeriodStatus::Verification, $this->period->refresh()->status);
        $this->assertNull($this->period->admin_submitted_at);
    }

    public function test_contractors_cannot_use_the_admin_verification_actions(): void
    {
        $this->actingAs($this->waiting)
            ->post(route('admin.verification.submit', $this->period))
            ->assertForbidden();
    }
}
