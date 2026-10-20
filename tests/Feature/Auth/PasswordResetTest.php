<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use App\Notifications\PasswordWasReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_the_super_admin_gets_a_new_default_password_by_email(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create(['email' => 'owner@arka.co']);

        $this->post('/forgot-password', ['email' => 'owner@arka.co'])
            ->assertSessionHas('status', PasswordResetLinkController::SENT);

        $defaultPassword = null;
        Notification::assertSentTo($superAdmin, PasswordWasReset::class, function (PasswordWasReset $notification) use ($superAdmin, &$defaultPassword) {
            $mail = $notification->toMail($superAdmin);
            $defaultPassword = str($mail->render())->match('/Arka-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}/')->toString();

            return $mail->actionUrl === route('login');
        });

        // The default password logs them in, then they must create their own.
        $this->assertTrue($superAdmin->refresh()->must_change_password);
        $this->post(route('login'), ['email' => 'owner@arka.co', 'password' => $defaultPassword]);
        $this->assertAuthenticatedAs($superAdmin);
        $this->get(route('dashboard'))->assertRedirect(route('password.setup'));
    }

    public function test_contractors_and_admins_get_nothing_and_the_same_answer(): void
    {
        Notification::fake();
        $contractor = User::factory()->create();
        $admin = User::factory()->admin()->create();

        // Same answer as for the Super Admin (and an unknown email), so the form never reveals who is who.
        foreach ([$contractor->email, $admin->email, 'nobody@gmail.com'] as $email) {
            $this->post('/forgot-password', ['email' => $email])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', PasswordResetLinkController::SENT);
        }

        Notification::assertNothingSent();
        $this->assertFalse($contractor->refresh()->must_change_password);
        $this->assertTrue(Hash::check('password', $admin->refresh()->password));
    }

    public function test_the_password_cannot_be_replaced_again_right_away(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->post('/forgot-password', ['email' => $superAdmin->email]);
        $firstPassword = $superAdmin->refresh()->password;

        // Someone who knows the address cannot keep replacing it.
        $this->post('/forgot-password', ['email' => $superAdmin->email])->assertSessionHas('status', PasswordResetLinkController::SENT);

        $this->assertSame($firstPassword, $superAdmin->refresh()->password);
        Notification::assertSentToTimes($superAdmin, PasswordWasReset::class, 1);
    }

    public function test_nothing_changes_when_the_email_cannot_be_sent(): void
    {
        // Nothing listens on port 1, so the SMTP connection is refused.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->post('/forgot-password', ['email' => $superAdmin->email])->assertSessionHas('status');

        // Nobody saw a new password, so the old one must keep working.
        $superAdmin->refresh();
        $this->assertTrue(Hash::check('password', $superAdmin->password));
        $this->assertFalse($superAdmin->must_change_password);
    }

    public function test_reset_links_are_gone(): void
    {
        $this->get('/reset-password/some-token')->assertNotFound();
        $this->post('/reset-password')->assertNotFound();
    }
}
