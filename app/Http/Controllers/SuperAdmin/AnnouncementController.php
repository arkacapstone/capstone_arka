<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Services\ActivityLogger;
use App\Services\Settings\SystemRules;
use App\Support\NotificationFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Notifications (Blueprint §3.1 module 10, §16): the Super Admin's own alerts
 * (approvals, payroll, requests, system), announcements to the workforce, and the system-wide
 * notification settings. In-app only; no email, SMS or push.
 */
class AnnouncementController extends Controller
{
    public const AUDIENCES = [
        'everyone' => 'Everyone',
        'admins' => 'Admins only',
        'employees' => 'Contractors only',
    ];

    public function index(Request $request, SystemRules $rules): Response
    {
        $filters = $request->validate(['filter' => ['nullable', 'in:all,unread']]);
        $unreadOnly = ($filters['filter'] ?? 'all') === 'unread';

        return Inertia::render('SuperAdmin/Notifications/Index', [
            'filter' => $unreadOnly ? 'unread' : 'all',
            'inbox' => ($unreadOnly ? $request->user()->unreadNotifications() : $request->user()->notifications())
                ->limit(30)
                ->get()
                ->map(fn (DatabaseNotification $notification) => NotificationFeed::present($notification))
                ->all(),
            'announcements' => ActivityLog::query()
                ->with('user:id,name')
                ->where('module', 'notifications')
                ->where('action', 'Posted announcement')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'details' => $log->details,
                    'by' => $log->user?->name,
                    'postedAt' => $log->created_at->toIso8601String(),
                ])->all(),
            'audiences' => collect(self::AUDIENCES)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'count' => $this->recipients($value, $request->user())->count(),
            ])->values()->all(),
            'settings' => RuleController::groups($rules, ['notifications'])[0]['rules'],
        ]);
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'heading' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', 'in:'.implode(',', array_keys(self::AUDIENCES))],
        ]);

        $recipients = $this->recipients($validated['audience'], $request->user())->get();

        Notification::send($recipients, new AnnouncementPosted($validated['heading'], $validated['body']));

        $activity->log(
            'notifications',
            'Posted announcement',
            null,
            "{$validated['heading']} · ".self::AUDIENCES[$validated['audience']]." ({$recipients->count()})",
        );

        return back()->with('success', "Announcement sent to {$recipients->count()} ".str('person')->plural($recipients->count()).'.');
    }

    /**
     * Active accounts in the audience, never the Super Admin posting it.
     *
     * @return Builder<User>
     */
    private function recipients(string $audience, User $author): Builder
    {
        return User::query()
            ->active()
            ->whereKeyNot($author->getKey())
            ->when($audience === 'admins', fn (Builder $query) => $query->withRole(UserRole::Admin))
            ->when($audience === 'employees', fn (Builder $query) => $query->withRole(UserRole::Employee))
            ->when($audience === 'everyone', fn (Builder $query) => $query->whereIn('role', [UserRole::Admin->value, UserRole::Employee->value]));
    }
}
