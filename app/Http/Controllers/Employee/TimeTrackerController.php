<?php

namespace App\Http\Controllers\Employee;

use App\Actions\TimeTracking\ManageTimer;
use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Services\TimeTracking\TimerBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contractor → Time Tracker (Contractor flow §III): one timer per client, several at once.
 */
class TimeTrackerController extends Controller
{
    public function index(Request $request, TimerBoard $board, ManageTimer $timers): Response
    {
        // Also done every minute by the scheduler; here so the board is right even without it.
        $timers->stopFinishedShifts($request->user());

        return Inertia::render('Employee/TimeTracker', ['board' => $board->for($request->user())]);
    }

    public function start(Request $request, ManageTimer $timers): RedirectResponse
    {
        $validated = $request->validate(['client_id' => ['required', 'integer']]);

        $log = $timers->start($request->user(), (int) $validated['client_id']);
        $log->load('client:id,client_name');

        return back()->with('success', "Timer started for {$log->client->client_name}.");
    }

    public function toggleBreak(Request $request, TimeLog $timeLog, ManageTimer $timers): RedirectResponse
    {
        $log = $timers->toggleBreak($this->own($request, $timeLog));

        return back()->with('success', $log->break_started_at ? 'Break started. Resume whenever you are ready.' : 'Welcome back — timer resumed.');
    }

    public function stop(Request $request, TimeLog $timeLog, ManageTimer $timers): RedirectResponse
    {
        $timers->stop($this->own($request, $timeLog));

        return back()->with('success', 'Timer stopped. Your attendance is updated.');
    }

    public function stopAll(Request $request, ManageTimer $timers): RedirectResponse
    {
        $stopped = $timers->stopAll($request->user());

        return back()->with('success', $stopped === 1 ? '1 timer stopped.' : "{$stopped} timers stopped.");
    }

    private function own(Request $request, TimeLog $timeLog): TimeLog
    {
        abort_unless($timeLog->employee_id === $request->user()->id, 404);

        return $timeLog;
    }
}
