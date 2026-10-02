<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Devotional;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_cards_reflect_todays_operations(): void
    {
        // Friday 25 Sep 2026, 11:30 PM
        $this->travelTo('2026-09-25 23:30:00');

        $day = User::factory()->create(['name' => 'Day Worker']);
        $night = User::factory()->create(['name' => 'Night Worker']);
        User::factory()->inactive()->create();

        Schedule::factory()->for($day, 'employee')->create();
        Schedule::factory()->graveyard()->for($night, 'employee')->create();

        Attendance::factory()->for($day, 'employee')->status(AttendanceStatus::Late)->create();
        Attendance::factory()->for($night, 'employee')->status(AttendanceStatus::Incomplete)->create(['time_in' => now()->setTime(22, 0)]);
        Devotional::factory()->for($day, 'employee')->create();

        // An Admin is also an employee, so their own devotional counts too.
        $this->actingAs(User::factory()->admin()->create(['name' => 'Zed Admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('employees.active', 2)
                ->where('employees.inactive', 1)
                ->where('attendance.late', 1)
                ->where('attendance.incomplete', 1)
                ->where('incomplete.total', 1)
                ->where('incomplete.items.0.employee', 'Night Worker')
                ->where('onShift.now', 1) // only the graveyard shift is running at 11:30 PM
                ->where('devotionals.pending', 2)
                ->where('devotionals.names', ['Night Worker', 'Zed Admin'])
                ->where('summary', fn (string $summary) => str_contains($summary, '1 attendance record needs a correction.'))
            );
    }

    public function test_a_graveyard_shift_is_still_on_after_midnight(): void
    {
        $this->travelTo('2026-09-26 02:00:00');

        Schedule::factory()->graveyard()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('onShift.now', 1));
    }
}
