<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Services\Settings\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    private function save(array $input): TestResponse
    {
        return $this->actingAs($this->superAdmin)->put(route('super-admin.rules.mail.update'), [
            'username' => 'arka.sender@gmail.com',
            'from_name' => 'ARKA',
            ...$input,
        ]);
    }

    public function test_the_super_admin_saves_the_sender_and_the_app_password_is_stored_encrypted(): void
    {
        $this->save(['password' => 'abcd efgh ijkl mnop'])->assertSessionHasNoErrors();

        $stored = DB::table('system_settings')->where('setting_key', 'mail_password')->value('setting_value');

        $this->assertStringNotContainsString('abcdefghijklmnop', $stored);
        $this->assertTrue(app(MailSettings::class)->isConfigured());

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.rules'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mail.username', 'arka.sender@gmail.com')
                ->where('mail.hasPassword', true)
                ->missing('mail.password'));
    }

    public function test_saved_settings_are_what_outgoing_mail_uses(): void
    {
        $this->save(['password' => 'abcd efgh ijkl mnop']);

        $this->app->forgetInstance(MailSettings::class);
        $this->app->make(MailSettings::class)->apply();

        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame('arka.sender@gmail.com', config('mail.mailers.smtp.username'));
        $this->assertSame('abcdefghijklmnop', config('mail.mailers.smtp.password'));
        $this->assertSame('arka.sender@gmail.com', config('mail.from.address'));
    }

    public function test_a_blank_password_keeps_the_saved_one(): void
    {
        $this->save(['password' => 'abcd efgh ijkl mnop']);
        $this->save(['password' => '', 'from_name' => 'ARKA Payroll'])->assertSessionHasNoErrors();

        $this->app->forgetInstance(MailSettings::class);
        $this->app->make(MailSettings::class)->apply();

        $this->assertSame('abcdefghijklmnop', config('mail.mailers.smtp.password'));
        $this->assertSame('ARKA Payroll', config('mail.from.name'));
    }

    public function test_the_first_save_needs_a_password(): void
    {
        $this->save(['password' => ''])->assertSessionHasErrors('password');
    }

    public function test_only_the_super_admin_can_change_the_sender(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('super-admin.rules.mail.update'), ['username' => 'x@gmail.com', 'password' => 'secret', 'from_name' => 'X'])
            ->assertForbidden();
    }
}
