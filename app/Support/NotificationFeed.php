<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Shapes database notifications for the bell, the dashboards and the Notifications page.
 */
final class NotificationFeed
{
    private const RECENT = 8;

    /**
     * @return array{unreadCount: int, recent: list<array<string, mixed>>}
     */
    public static function for(User $user): array
    {
        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()
                ->limit(self::RECENT)
                ->get()
                ->map(fn (DatabaseNotification $notification) => self::present($notification))
                ->all(),
        ];
    }

    /**
     * @return array{id: string, title: string, message: ?string, url: ?string, category: string, readAt: ?string, createdAt: string}
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notification',
            'message' => $notification->data['message'] ?? null,
            'url' => $notification->data['url'] ?? null,
            'category' => $notification->data['category'] ?? 'account',
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at->toIso8601String(),
        ];
    }
}
