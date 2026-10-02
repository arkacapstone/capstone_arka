<?php

namespace App\Services\Dashboard\Widgets;

use App\Models\ActivityLog;
use App\Services\Dashboard\Contracts\DashboardWidget;

/**
 * The latest important system actions (Activity Logs module).
 */
class RecentActivityWidget implements DashboardWidget
{
    private const LIMIT = 6;

    public function key(): string
    {
        return 'recentActivity';
    }

    /**
     * @return list<array{id: int, action: string, module: string, details: ?string, user: ?string, at: string}>
     */
    public function data(): array
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'details' => $log->details,
                'user' => $log->user?->name,
                'at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
