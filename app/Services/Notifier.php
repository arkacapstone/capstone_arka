<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * Sends a notification to everyone holding a role, e.g. all active Admins when an
 * employee asks for an attendance correction.
 */
class Notifier
{
    public function admins(Notification $notification, ?User $except = null): void
    {
        $this->role(UserRole::Admin, $notification, $except);
    }

    public function superAdmins(Notification $notification, ?User $except = null): void
    {
        $this->role(UserRole::SuperAdmin, $notification, $except);
    }

    private function role(UserRole $role, Notification $notification, ?User $except): void
    {
        $recipients = User::query()
            ->withRole($role)
            ->active()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();

        Notifications::send($recipients, $notification);
    }
}
