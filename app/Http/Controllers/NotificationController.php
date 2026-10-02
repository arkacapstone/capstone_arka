<?php

namespace App\Http\Controllers;

use App\Support\NotificationFeed;
use App\Support\Paginated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications are informational only; they never replace the records or approvals (Blueprint §16).
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['filter' => ['nullable', 'in:all,unread']]);
        $unreadOnly = ($filters['filter'] ?? 'all') === 'unread';

        $notifications = ($unreadOnly ? $request->user()->unreadNotifications() : $request->user()->notifications())
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => NotificationFeed::present($notification));

        return Inertia::render('Notifications/Index', [
            'items' => Paginated::from($notifications),
            'filter' => $unreadOnly ? 'unread' : 'all',
        ]);
    }

    /**
     * The bell polls this so new notifications appear without a page reload.
     */
    public function feed(Request $request): JsonResponse
    {
        return response()->json(NotificationFeed::for($request->user()));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * Marks one as read; with `open`, continues to the page the notification is about.
     */
    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return $request->boolean('open') && is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : back();
    }
}
