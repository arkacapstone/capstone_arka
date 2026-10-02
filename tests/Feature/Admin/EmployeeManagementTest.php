<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_sees_employees_only(): void
    {
        User::factory()->count(3)->create();
        User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.employees.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Employees/Index')->has('employees.data', 3)->where('counts.total', 3));
    }

    public function test_admin_invites_a_contractor_by_email(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan.delacruz@gmail.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertSessionMissing('credentials')
            ->assertSessionMissing('invitation');

        $employee = User::query()->where('email', 'juan.delacruz@gmail.com')->sole();

        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertTrue($employee->isInvited());
        $this->assertTrue($employee->must_change_password);
        $this->assertNull($employee->email_verified_at);

        Notification::assertSentTo(
            $employee,
            AccountInvitation::class,
            fn (AccountInvitation $notification) => str_contains($notification->toMail($employee)->actionUrl, '/invitation/'),
        );
    }

    public function test_the_invite_link_is_shown_when_the_email_cannot_be_sent(): void
    {
        // Nothing listens on port 1, so the SMTP connection is refused.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Ana Reyes',
                'email' => 'ana@gmail.com',
            ])
            ->assertSessionHas('warning')
            ->assertSessionHas('invitation.url', fn (string $url) => str_contains($url, '/invitation/'))
            ->assertSessionHas('invitation.defaultPassword');

        $this->assertDatabaseHas('users', ['email' => 'ana@gmail.com']);
    }

    public function test_resending_an_invite_replaces_the_old_link(): void
    {
        Notification::fake();
        $employee = User::factory()->profileIncomplete()->create(['invitation_token' => hash('sha256', 'old-token'), 'invitation_sent_at' => now()]);

        $this->actingAs($this->admin)
            ->post(route('admin.employees.invitation', $employee))
            ->assertSessionHas('success');

        $this->assertNotSame(hash('sha256', 'old-token'), $employee->refresh()->invitation_token);
        Notification::assertSentTo($employee, AccountInvitation::class);

        $this->get(route('invitation.verify', 'old-token'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertNull($employee->refresh()->email_verified_at);
    }

    public function test_admins_cannot_create_admin_accounts(): void
    {
        $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@makarius.co',
            'employment_type' => 'full_time',
            'role' => 'company_admin',
        ]);

        $this->assertSame(UserRole::Employee, User::query()->where('email', 'sneaky@makarius.co')->sole()->role);

        $this->actingAs($this->admin)
            ->post(route('super-admin.workforce.admins.store'), ['name' => 'X', 'email' => 'x@makarius.co'])
            ->assertForbidden();
    }

    public function test_admins_cannot_manage_other_admins_through_employee_routes(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.employees.status', $otherAdmin), ['status' => 'inactive'])
            ->assertNotFound();
    }

    public function test_employee_page_shows_schedules_and_attendance_but_no_rates(): void
    {
        $employee = User::factory()->create();
        Rate::factory()->for($employee, 'employee')->create();
        Schedule::factory()->for($employee, 'employee')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.employees.show', $employee))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Show')
                ->has('schedules', 1)
                ->has('attendanceMonth.counts')
                ->missing('employee.assignments')
                ->missing('rateHistory')
            );
    }

    public function test_admin_deactivates_an_employee(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.employees.status', $employee), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $employee->refresh()->status);
    }
}
