<?php

namespace Tests\Feature\Employee;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\LeaveRequestDecided;
use App\Notifications\LeaveRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('local');
        $this->travelTo('2026-09-25 10:00:00');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'paid' => 1,
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-29',
            'reason' => 'Family matter',
            'client_informed' => 0,
            ...$overrides,
        ];
    }

    public function test_a_leave_request_goes_to_the_super_admin(): void
    {
        $employee = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($employee)->post(route('employee.leave.store'), $this->payload())->assertSessionHasNoErrors();

        $leave = LeaveRequest::sole();
        $this->assertSame(LeaveRequestStatus::PendingApproval, $leave->status);
        $this->assertTrue($leave->leaveType->is_paid);

        Notification::assertSentTo($superAdmin, LeaveRequestSubmitted::class);
    }

    public function test_client_informed_without_proof_is_flagged_not_rejected(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->post(route('employee.leave.store'), $this->payload(['client_informed' => 1]));

        $this->assertSame(LeaveRequestStatus::NeedsVerification, LeaveRequest::sole()->status);
        Notification::assertSentTo($employee, LeaveRequestDecided::class);
    }

    public function test_attaching_proof_keeps_it_pending_approval(): void
    {
        $this->actingAs(User::factory()->create())->post(route('employee.leave.store'), $this->payload([
            'client_informed' => 1,
            'proof' => UploadedFile::fake()->create('client-ok.png', 50, 'image/png'),
        ]));

        $leave = LeaveRequest::sole();
        $this->assertSame(LeaveRequestStatus::PendingApproval, $leave->status);
        Storage::disk('local')->assertExists($leave->proof_path);
    }

    public function test_an_open_request_can_be_withdrawn_but_a_decided_one_cannot(): void
    {
        $employee = User::factory()->create();
        $open = LeaveRequest::factory()->for($employee, 'employee')->create();
        $decided = LeaveRequest::factory()->for($employee, 'employee')->approved()->create();

        $this->actingAs($employee)->post(route('employee.leave.cancel', $open));
        $this->post(route('employee.leave.cancel', $decided));

        $this->assertSame(LeaveRequestStatus::Cancelled, $open->fresh()->status);
        $this->assertSame(LeaveRequestStatus::Approved, $decided->fresh()->status);

        $this->actingAs(User::factory()->create())->post(route('employee.leave.cancel', $open))->assertNotFound();
    }

    public function test_approval_marks_scheduled_days_as_leave_and_notifies_the_employee(): void
    {
        $employee = User::factory()->create();
        $schedule = Schedule::factory()->for($employee, 'employee')->create(); // Mon–Fri
        $leave = LeaveRequest::factory()->for($employee, 'employee')->create([
            'leave_type_id' => LeaveType::factory()->create(['is_paid' => true]),
            'start_date' => '2026-09-25', // Fri
            'end_date' => '2026-09-28', // Mon
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.requests.leave.approve', $leave))
            ->assertSessionHasNoErrors();

        $this->assertSame(LeaveRequestStatus::Approved, $leave->fresh()->status);

        $days = Attendance::query()->where('employee_id', $employee->id)->orderBy('date')->get();
        $this->assertSame(['2026-09-25', '2026-09-28'], $days->map(fn ($day) => $day->date->toDateString())->all(), 'Weekend days are not scheduled.');
        $this->assertTrue($days->every(fn ($day) => $day->status === AttendanceStatus::PaidLeave && $day->client_id === $schedule->client_id));

        Notification::assertSentTo($employee, LeaveRequestDecided::class);
    }

    public function test_only_the_super_admin_decides_leave(): void
    {
        $leave = LeaveRequest::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('super-admin.requests.leave.approve', $leave))
            ->assertForbidden();
    }
}
