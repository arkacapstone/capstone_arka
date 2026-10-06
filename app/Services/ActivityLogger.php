<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records important system actions for accountability (Activity Logs module).
 */
class ActivityLogger
{
    public function __construct(private readonly Request $request) {}

    public function log(string $module, string $action, ?Model $subject = null, ?string $details = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $this->request->user()?->id,
            'module' => $module,
            'action' => $action,
            'reference_table' => $subject?->getTable(),
            'reference_id' => $subject?->getKey(),
            'details' => $details,
        ]);
    }
}
