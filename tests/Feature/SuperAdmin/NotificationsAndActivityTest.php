<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Notifications\DevotionalReminder;
use App\Services\Settings\SystemRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationsAndActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_the_notifications_module_shows_alerts_audiences_and_settings(): void
    {
        User::factory()->count(2)->create();
        User::factory()->admin()->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.notifications'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Notifications/Index')
                ->where('audiences.0', ['value' => 'everyone', 'label' => 'Everyone', 'count' => 3])
                ->where('audiences.1.count', 1)
                ->where('audiences.2.count', 2)
                ->has('settings', 4));
    }

    public function test_an_announcement_reaches_only_its_audience_and_is_logged(): void
    {
        Notification::fake();
        $employee = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.notifications.announce'), ['heading' => 'Holiday', 'body' => 'Office closed Monday.', 'audience' => 'employees'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($employee, AnnouncementPosted::class);
        Notification::assertNotSentTo([$admin, $inactive, $this->superAdmin], AnnouncementPosted::class);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.notifications'))
            ->assertInertia(fn (Assert $page) => $page->has('announcements', 1));
    }

    public function test_devotional_reminders_follow_the_notification_settings(): void
    {
        Notification::fake();
        $employee = User::factory()->create();
        app(SystemRules::class)->update(['devotional_reminders_enabled' => false], $this->superAdmin);

        $this->travelTo('2026-09-25 19:00:00');
        $this->artisan('arka:devotional-reminders')->assertSuccessful();
        Notification::assertNotSentTo($employee, DevotionalReminder::class);

        app(SystemRules::class)->update(['devotional_reminders_enabled' => true, 'devotional_reminder_time' => '20:00'], $this->superAdmin);
        $this->artisan('arka:devotional-reminders')->assertSuccessful();
        Notification::assertNotSentTo($employee, DevotionalReminder::class);

        $this->travelTo('2026-09-25 20:15:00');
        $this->artisan('arka:devotional-reminders')->assertSuccessful();
        Notification::assertSentTo($employee, DevotionalReminder::class);
    }

    public function test_signing_in_is_recorded_in_the_activity_log(): void
    {
        $employee = User::factory()->create();

        $this->post(route('login'), ['email' => $employee->email, 'password' => 'password']);

        $this->assertDatabaseHas('activity_logs', ['module' => 'auth', 'action' => 'Signed in', 'user_id' => $employee->id]);
    }

    public function test_activity_logs_can_be_filtered_by_module_person_and_text(): void
    {
        $admin = User::factory()->admin()->create();
        ActivityLog::create(['user_id' => $admin->id, 'module' => 'workforce', 'action' => 'Created employee account', 'details' => 'Christian Mae']);
        ActivityLog::create(['user_id' => $this->superAdmin->id, 'module' => 'payroll', 'action' => 'Released payslips', 'details' => 'Sep 11 – Sep 25']);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.activity-logs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/ActivityLogs/Index')->has('logs.data', 2)->has('modules', 2));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.activity-logs', ['module' => 'payroll']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'Released payslips'));

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.activity-logs', ['user' => $admin->id, 'search' => 'Christian']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.moduleLabel', 'Accounts & workforce'));
    }

    public function test_admins_cannot_open_activity_logs(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('super-admin.activity-logs'))->assertForbidden();
    }
}
