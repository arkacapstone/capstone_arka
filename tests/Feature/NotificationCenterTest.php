<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\ClientAssignmentRequest;
use App\Models\Devotional;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\AdminDailyDigest;
use App\Notifications\ClientAssigned;
use App\Notifications\DevotionalReminder;
use App\Notifications\EmployeeAwaitingFirstLogin;
use App\Notifications\ScheduleOverlapDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bell_feed_and_shared_props_include_where_each_notification_leads(): void
    {
        $employee = User::factory()->create();
        $employee->notify(new DevotionalReminder);

        $this->actingAs($employee)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('recent.0.title', "Today's devotional")
            ->assertJsonPath('recent.0.category', 'devotional')
            ->assertJsonPath('recent.0.url', '/employee/devotional');

        $this->get(route('employee.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.unreadCount', 1));
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_its_page(): void
    {
        $employee = User::factory()->create();
        $employee->notify(new DevotionalReminder);
        $notification = $employee->notifications()->sole();

        $this->actingAs($employee)
            ->post(route('notifications.read', $notification->id), ['open' => true])
            ->assertRedirect('/employee/devotional');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_notifications_page_filters_unread(): void
    {
        $employee = User::factory()->create();
        $employee->notify(new DevotionalReminder);
        $employee->notify(new DevotionalReminder);
        $employee->notifications()->first()->markAsRead();

        $this->actingAs($employee)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertInertia(fn (Assert $page) => $page->where('filter', 'unread')->has('items.data', 1));
    }

    public function test_admins_hear_about_new_employees_but_not_about_their_own_actions(): void
    {
        Notification::fake();

        $creator = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($creator)->post(route('admin.employees.store'), [
            'name' => 'Nina Cruz',
            'email' => 'nina@arka.co',
            'employment_type' => 'full_time',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($otherAdmin, EmployeeAwaitingFirstLogin::class);
        Notification::assertNotSentTo($creator, EmployeeAwaitingFirstLogin::class);
    }

    public function test_the_employee_is_told_about_a_new_client_assignment(): void
    {
        Notification::fake();

        $employee = User::factory()->create();
        $request = ClientAssignmentRequest::create([
            'employee_id' => $employee->id,
            'client_id' => Client::factory()->create()->id,
            'requested_by' => User::factory()->admin()->create()->id,
            'start_date' => now()->toDateString(),
            'status' => ClientAssignmentRequest::STATUS_PENDING,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.requests.clients.approve', $request), [
                'gross_pay' => 20000,
                'pay_frequency' => 'semi_monthly',
                'working_days' => 11,
                'hours_per_day' => 8,
                'effective_date' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($employee, ClientAssigned::class);
    }

    public function test_other_admins_hear_about_a_saved_schedule_overlap(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        [$first, $second] = Client::factory()->count(2)->create();
        Rate::factory()->for($employee, 'employee')->for($first)->create();
        Rate::factory()->for($employee, 'employee')->for($second)->create();
        Schedule::factory()->for($employee, 'employee')->for($first)->create();

        $this->actingAs($admin)->post(route('admin.scheduling.store'), [
            'employee_id' => $employee->id,
            'client_id' => $second->id,
            'working_days' => ['mon', 'tue'],
            'start_time' => '10:00',
            'end_time' => '14:00',
            'start_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($otherAdmin, ScheduleOverlapDetected::class);
        Notification::assertNotSentTo($admin, ScheduleOverlapDetected::class);
    }

    public function test_the_evening_devotional_reminder_goes_once_to_people_who_have_not_submitted(): void
    {
        Notification::fake();
        $this->travelTo('2026-09-25 18:00:00');

        $done = User::factory()->create();
        $pending = User::factory()->create();
        $admin = User::factory()->admin()->create();
        Devotional::factory()->for($done, 'employee')->create(['date' => '2026-09-25']);

        $this->artisan('arka:devotional-reminders')->assertSuccessful();
        $this->artisan('arka:devotional-reminders')->assertSuccessful();

        Notification::assertSentToTimes($pending, DevotionalReminder::class, 1);
        Notification::assertSentTo($admin, DevotionalReminder::class);
        Notification::assertNotSentTo($done, DevotionalReminder::class);
    }

    public function test_the_admin_digest_reports_yesterdays_incomplete_attendance(): void
    {
        Notification::fake();
        $this->travelTo('2026-09-26 07:30:00');

        $admin = User::factory()->admin()->create();
        Attendance::factory()->status(AttendanceStatus::Incomplete)->create(['date' => '2026-09-25']);

        $this->artisan('arka:admin-digest')->assertSuccessful();

        Notification::assertSentTo($admin, AdminDailyDigest::class, function (AdminDailyDigest $digest) use ($admin) {
            $data = $digest->toArray($admin);

            return str_contains($data['message'], '1 attendance record is Incomplete') && str_contains($data['url'], 'status=incomplete');
        });
    }
}
