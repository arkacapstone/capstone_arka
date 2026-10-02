<?php

namespace Tests\Feature\Employee;

use App\Models\Devotional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DevotionalSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-09-25 20:00:00');
    }

    public function test_an_employee_submits_todays_devotional(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->post(route('employee.devotionals.store'), [
                'title' => 'Psalm 23',
                'file' => UploadedFile::fake()->create('psalm.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $devotional = Devotional::sole();
        $this->assertSame('2026-09-25', $devotional->date->toDateString());
        $this->assertSame('psalm.pdf', $devotional->file_name);
        Storage::disk('local')->assertExists($devotional->file_path);

        $this->get(route('employee.devotionals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submission.title', 'Psalm 23')
                ->where('thisMonth', 1)
                ->where('daysSoFar', 25)
            );
    }

    public function test_submitting_again_the_same_day_replaces_the_file(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->post(route('employee.devotionals.store'), ['title' => 'First', 'file' => UploadedFile::fake()->create('first.png', 50, 'image/png')]);
        $firstPath = Devotional::sole()->file_path;

        $this->post(route('employee.devotionals.store'), ['title' => 'Second', 'file' => UploadedFile::fake()->create('second.jpg', 50, 'image/jpeg')]);

        $devotional = Devotional::sole();
        $this->assertSame('Second', $devotional->title);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($devotional->file_path);
    }

    public function test_only_documents_and_images_up_to_10_mb_are_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('employee.devotionals.store'), ['title' => 'x', 'file' => UploadedFile::fake()->create('run.exe', 10)])
            ->assertSessionHasErrors('file');

        $this->post(route('employee.devotionals.store'), ['title' => 'x', 'file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_employees_only_open_their_own_files(): void
    {
        $theirs = Devotional::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('employee.devotionals.file', $theirs))
            ->assertNotFound();
    }
}
