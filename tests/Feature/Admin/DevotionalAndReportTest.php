<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Devotional;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DevotionalAndReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25 10:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Zed Admin']);
    }

    public function test_devotional_list_shows_who_submitted_and_late_uploads(): void
    {
        $onTime = User::factory()->create(['name' => 'A On Time']);
        $late = User::factory()->create(['name' => 'B Late']);
        User::factory()->create(['name' => 'C Missing']);

        Devotional::factory()->for($onTime, 'employee')->create(['date' => '2026-09-24', 'submitted_at' => '2026-09-24 20:00:00']);
        Devotional::factory()->for($late, 'employee')->create(['date' => '2026-09-24', 'submitted_at' => '2026-09-25 00:30:00']);

        $this->actingAs($this->admin)
            ->get(route('admin.devotionals.index', ['date' => '2026-09-24']))
            ->assertInertia(fn (Assert $page) => $page
                // The Admin is also an employee, so they are expected to submit too.
                ->where('summary', ['expected' => 4, 'submitted' => 2])
                ->where('rows.data.0.devotional.late', false)
                ->where('rows.data.1.devotional.late', true)
                ->where('rows.data.2.devotional', null)
            );
    }

    public function test_admin_views_a_devotional_file_read_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('devotionals/proof.pdf', UploadedFile::fake()->create('proof.pdf', 10)->getContent());
        $devotional = Devotional::factory()->create(['file_path' => 'devotionals/proof.pdf', 'file_name' => 'proof.pdf']);

        $this->actingAs($this->admin)
            ->get(route('admin.devotionals.file', $devotional))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=proof.pdf');
    }

    public function test_attendance_report_summarises_per_employee(): void
    {
        $employee = User::factory()->create(['name' => 'Maria']);
        Attendance::factory()->for($employee, 'employee')->status(AttendanceStatus::Present)->create(['date' => '2026-09-22']);
        Attendance::factory()->for($employee, 'employee')->status(AttendanceStatus::Late)->create(['date' => '2026-09-23']);
        Attendance::factory()->for($employee, 'employee')->status(AttendanceStatus::PaidLeave)->create(['date' => '2026-09-24']);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.show', ['report' => 'attendance', 'from' => '2026-09-21', 'to' => '2026-09-25']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Show')
                ->where('summary.rows.0', ['Maria', 1, 1, 0, 0, 1, 0])
                ->has('details.rows', 3)
            );
    }

    public function test_leave_summary_counts_approved_days_inside_the_period(): void
    {
        $employee = User::factory()->create(['name' => 'Jose']);
        $paid = LeaveType::factory()->create(['is_paid' => true]);
        LeaveRequest::factory()->for($employee, 'employee')->for($paid)->create([
            'start_date' => '2026-09-24',
            'end_date' => '2026-09-28',
            'status' => LeaveRequestStatus::Approved,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.show', ['report' => 'leave', 'from' => '2026-09-01', 'to' => '2026-09-25']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.rows.0', ['Jose', 2, 0, 2]));
    }

    public function test_reports_export_as_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.show', ['report' => 'devotional', 'export' => 'csv']));

        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('Devotional Report', $response->streamedContent());
    }

    public function test_unknown_reports_are_not_found(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'payroll'))->assertNotFound();
    }
}
