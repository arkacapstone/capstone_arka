<?php

namespace Tests\Feature\Workforce;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\PasswordWasReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetByAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_resets_a_contractors_forgotten_password(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $contractor = User::factory()->create(['email' => 'mariella@gmail.com']);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $contractor->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($admin)
            ->post(route('admin.employees.password-reset', $contractor))
            ->assertSessionHas('success', 'Password reset. Login details sent to mariella@gmail.com.')
            ->assertSessionMissing('invitation');

        $contractor->refresh();
        $this->assertTrue($contractor->must_change_password);
        $this->assertNotNull($contractor->email_verified_at, 'A reset keeps the email verified.');
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertTrue(ActivityLog::query()->where('action', 'Reset Contractor password')->where('reference_id', $contractor->id)->exists());

        // The email has the default password and a button to the login page.
        $defaultPassword = null;
        Notification::assertSentTo($contractor, PasswordWasReset::class, function (PasswordWasReset $notification) use ($contractor, &$defaultPassword) {
            $mail = $notification->toMail($contractor);
            $defaultPassword = str($mail->render())->match('/Arka-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}/')->toString();

            return $mail->actionUrl === route('login');
        });

        // The old password stops working; the default one logs them in and asks for their own password.
        auth()->logout();
        $this->post(route('login'), ['email' => 'mariella@gmail.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => 'mariella@gmail.com', 'password' => $defaultPassword]);
        $this->assertAuthenticatedAs($contractor);
        $this->get(route('dashboard'))->assertRedirect(route('password.setup'));
    }

    public function test_the_super_admin_resets_an_admins_password(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.workforce.admins.password-reset', $admin))
            ->assertSessionHas('success');

        $this->assertTrue($admin->refresh()->must_change_password);
        Notification::assertSentTo($admin, PasswordWasReset::class);
    }

    public function test_nobody_can_reset_the_super_admins_password_or_another_admins(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        // Admins only manage contractors; the Super Admin uses "Forgot your password?" on the login page.
        $this->actingAs($admin)->post(route('admin.employees.password-reset', $superAdmin))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.employees.password-reset', $otherAdmin))->assertNotFound();
        $this->actingAs($superAdmin)->post(route('super-admin.workforce.admins.password-reset', $superAdmin))->assertNotFound();

        $this->assertFalse($superAdmin->refresh()->must_change_password);
        $this->assertFalse($otherAdmin->refresh()->must_change_password);
    }

    public function test_invited_and_deactivated_accounts_cannot_be_reset(): void
    {
        $admin = User::factory()->admin()->create();
        $invited = User::factory()->unverified()->create(['invitation_token' => hash('sha256', 'token'), 'invitation_sent_at' => now()]);
        $inactive = User::factory()->inactive()->create();

        // An invited contractor gets "Resend invite" instead; a deactivated one cannot log in anyway.
        $this->actingAs($admin)->post(route('admin.employees.password-reset', $invited))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.employees.password-reset', $inactive))->assertNotFound();
    }

    public function test_the_new_password_is_shown_when_the_email_cannot_be_sent(): void
    {
        // Nothing listens on port 1, so the SMTP connection is refused.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $admin = User::factory()->admin()->create();
        $contractor = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.employees.password-reset', $contractor))
            ->assertSessionHas('warning', 'The password reset email could not be sent. Copy the login details below and share them privately.')
            ->assertSessionHas('invitation.kind', 'reset')
            ->assertSessionHas('invitation.url', route('login'))
            ->assertSessionHas('invitation.defaultPassword');

        $this->assertTrue($contractor->refresh()->must_change_password);
    }
}
