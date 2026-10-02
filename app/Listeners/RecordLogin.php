<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Login records for Activity Logs (Blueprint §3.1 module 11).
 */
class RecordLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        ActivityLog::create([
            'user_id' => $event->user->id,
            'module' => 'auth',
            'action' => 'Signed in',
            'reference_table' => $event->user->getTable(),
            'reference_id' => $event->user->id,
            'details' => $event->user->role?->label(),
            'ip_address' => $this->request->ip(),
        ]);
    }
}
