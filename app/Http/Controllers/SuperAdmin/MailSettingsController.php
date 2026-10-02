<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Settings\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * System & Rules → Email sender: the Gmail username and App Password ARKA sends invite links
 * and password resets with. Only the Super Admin can see or change it.
 */
class MailSettingsController extends Controller
{
    public function update(Request $request, MailSettings $settings, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'email', 'max:255'],
            'password' => [$settings->hasPassword() ? 'nullable' : 'required', 'string', 'max:64'],
            'from_name' => ['required', 'string', 'max:100'],
        ], [], [
            'username' => 'Gmail username',
            'password' => 'App Password',
            'from_name' => 'sender name',
        ]);

        $settings->save($validated['username'], $validated['password'] ?? null, $validated['from_name'], $request->user());

        $activity->log('rules', 'Updated email sender', null, $validated['username'].(filled($validated['password'] ?? null) ? ' (new App Password)' : ''));

        return back()->with('success', 'Email sender saved. Send a test email to check it.');
    }

    /**
     * Sends a short email to the sender's own inbox to prove the username and App Password work.
     */
    public function test(MailSettings $settings): RedirectResponse
    {
        if (! $settings->isConfigured()) {
            return back()->with('warning', 'Save the Gmail username and App Password first.');
        }

        try {
            Mail::raw(
                'This is a test email from ARKA. Invite links and password resets will be sent from this account.',
                fn (Message $message) => $message->to($settings->username())->subject('ARKA test email'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', 'The test email could not be sent. Check the Gmail username and App Password. ('.Str::limit($exception->getMessage(), 160).')');
        }

        return back()->with('success', "Test email sent to {$settings->username()}. Check that inbox.");
    }
}
