<?php

namespace Tests\Feature\Auth;

use App\Actions\Accounts\SendInvitation;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string, 2: string} The contractor, their default password and the verify link.
     */
    private function invited(array $attributes = []): array
    {
        Notification::fake();

        $contractor = User::factory()->profileIncomplete()->create(['email_verified_at' => null, ...$attributes]);
        $invitation = app(SendInvitation::class)->handle($contractor);

        return [$contractor->refresh(), $invitation['defaultPassword'], $invitation['url']];
    }

    public function test_the_invite_email_has_the_username_default_password_and_verify_button(): void
    {
        [$contractor, $defaultPassword, $url] = $this->invited();

        Notification::assertSentTo($contractor, AccountInvitation::class, function (AccountInvitation $notification) use ($contractor, $defaultPassword, $url) {
            $mail = $notification->toMail($contractor);
            $lines = implode(' ', $mail->introLines);

            return str_contains($lines, "**Username:** {$contractor->email}")
                && str_contains($lines, "**Default password:** {$defaultPassword}")
                && $mail->actionText === 'Verify email & log in'
                && $mail->actionUrl === $url;
        });

        $this->assertTrue($contractor->isInvited());
        $this->assertTrue($contractor->must_change_password);
        $this->assertTrue(Hash::check($defaultPassword, $contractor->password));
    }

    public function test_the_contractor_cannot_log_in_before_verifying_their_email(): void
    {
        [$contractor, $defaultPassword] = $this->invited();

        $this->post(route('login'), ['email' => $contractor->email, 'password' => $defaultPassword])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_verify_button_verifies_the_email_and_opens_the_login_page(): void
    {
        [$contractor, $defaultPassword, $url] = $this->invited();

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $contractor->refresh();
        $this->assertFalse($contractor->isInvited());
        $this->assertNotNull($contractor->email_verified_at);
        $this->assertGuest();

        $this->post(route('login'), ['email' => $contractor->email, 'password' => $defaultPassword]);
        $this->assertAuthenticatedAs($contractor);
    }

    public function test_after_verifying_the_default_password_and_profile_come_before_the_dashboard(): void
    {
        [$contractor, $defaultPassword, $url] = $this->invited();

        $this->get($url);
        $this->post(route('login'), ['email' => $contractor->email, 'password' => $defaultPassword]);

        $this->get(route('dashboard'))->assertRedirect(route('password.setup'));

        $this->put(route('password.setup.update'), [
            'password' => 'a-new-Secure-passw0rd',
            'password_confirmation' => 'a-new-Secure-passw0rd',
        ]);

        $this->assertFalse($contractor->refresh()->must_change_password);
        $this->get(route('employee.dashboard'))->assertRedirect(route('profile.setup'));
    }

    public function test_the_new_password_must_match_differ_from_the_default_and_replace_it(): void
    {
        [$contractor, $defaultPassword, $url] = $this->invited();

        $this->get($url);
        $this->post(route('login'), ['email' => $contractor->email, 'password' => $defaultPassword]);

        $this->put(route('password.setup.update'), [
            'password' => 'a-new-Secure-passw0rd',
            'password_confirmation' => 'a-different-Secure-passw0rd',
        ])->assertSessionHasErrors('password');

        $this->put(route('password.setup.update'), [
            'password' => $defaultPassword,
            'password_confirmation' => $defaultPassword,
        ])->assertSessionHasErrors('password');

        $this->assertTrue($contractor->refresh()->must_change_password);

        $this->put(route('password.setup.update'), [
            'password' => 'a-new-Secure-passw0rd',
            'password_confirmation' => 'a-new-Secure-passw0rd',
        ])->assertRedirect(route('profile.setup'));

        $this->post(route('logout'));

        $this->post(route('login'), ['email' => $contractor->email, 'password' => $defaultPassword])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => $contractor->email, 'password' => 'a-new-Secure-passw0rd']);
        $this->assertAuthenticatedAs($contractor);
        $this->get(route('dashboard'))->assertRedirect(route('profile.setup'));
    }

    public function test_the_verify_link_works_only_once(): void
    {
        [, , $url] = $this->invited();

        $this->get($url)->assertSessionHas('status');

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_an_expired_link_does_not_verify_the_email(): void
    {
        [$contractor, , $url] = $this->invited();
        $contractor->forceFill(['invitation_sent_at' => now()->subDays(User::INVITATION_VALID_DAYS + 1)])->save();

        $this->get($url)->assertSessionHasErrors('email');

        $this->assertTrue($contractor->refresh()->isInvited());
        $this->assertNull($contractor->email_verified_at);
    }

    public function test_a_deactivated_account_cannot_verify(): void
    {
        [$contractor, , $url] = $this->invited();
        $contractor->forceFill(['status' => UserStatus::Inactive->value])->save();

        $this->get($url)->assertSessionHasErrors('email');

        $this->assertNull($contractor->refresh()->email_verified_at);
    }
}
