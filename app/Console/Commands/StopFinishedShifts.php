<?php

namespace App\Console\Commands;

use App\Actions\TimeTracking\ManageTimer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Stops timers still running when their shift ends, at the scheduled end time. Overtime is paid
 * through overtime tickets, never by a timer left running.
 */
#[Signature('arka:stop-finished-shifts')]
#[Description('Stop timers that are still running after their shift ended')]
class StopFinishedShifts extends Command
{
    public function handle(ManageTimer $timers): int
    {
        $stopped = $timers->stopFinishedShifts();

        $this->info("Stopped {$stopped} ".str('timer')->plural($stopped).'.');

        return self::SUCCESS;
    }
}
