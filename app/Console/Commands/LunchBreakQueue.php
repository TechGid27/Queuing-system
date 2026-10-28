<?php

namespace App\Console\Commands;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Services\QueueTransitionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LunchBreakQueue extends Command
{
    protected $signature = 'queue:lunch-break';

    protected $description = 'Automatically pause and resume active department queues at lunch';

    public function handle(QueueTransitionService $transitions): int
    {
        $startTime = DB::table('settings')->where('key', 'lunch_break_start')->value('value') ?? '12:00';
        $endTime = DB::table('settings')->where('key', 'lunch_break_end')->value('value') ?? '13:30';
        $now = now();
        $todayStart = Carbon::createFromFormat('H:i', $startTime, $now->timezone)->setDateFrom($now);
        $todayEnd = Carbon::createFromFormat('H:i', $endTime, $now->timezone)->setDateFrom($now);

        if ($now->lt($todayStart)) {
            $this->info('No lunch break action needed at '.$now->format('H:i').'.');

            return Command::SUCCESS;
        }

        // Window-based instead of exact-minute matching so a missed scheduler
        // tick (deploy, restart, slow worker) cannot skip the pause/resume.
        if ($now->lt($todayEnd)) {
            $this->pauseCountersForLunch($transitions);
            $this->info("Active department queues paused for lunch (started {$startTime}).");

            return Command::SUCCESS;
        }

        $this->resumeCountersForLunch($transitions);
        $this->info("Active department queues resumed after lunch (ended {$endTime}).");

        return Command::SUCCESS;
    }

    private function pauseCountersForLunch(QueueTransitionService $transitions): void
    {
        $departments = Department::active()->get();

        foreach ($departments as $department) {
            $counters = $department->counters()->where('auto_pause_enabled', true)->get();

            if ($counters->isEmpty()) {
                $this->setPaused(true, $transitions);

                continue;
            }

            foreach ($counters as $counter) {
                if (! $counter->queue_paused) {
                    $transitions->setCounterPaused($counter, true, null, 'lunch');
                }
            }
        }
    }

    private function resumeCountersForLunch(QueueTransitionService $transitions): void
    {
        $departments = Department::active()->get();

        foreach ($departments as $department) {
            $counters = $department->counters()->where('auto_pause_enabled', true)->get();

            if ($counters->isEmpty()) {
                $this->setPaused(false, $transitions);

                continue;
            }

            foreach ($counters as $counter) {
                if ($counter->queue_paused) {
                    $transitions->setCounterPaused($counter, false, null, 'lunch');
                }
            }
        }
    }

    private function setPaused(bool $paused, QueueTransitionService $transitions): void
    {
        $departments = Department::active()->where('auto_pause_enabled', true)->get();

        foreach ($departments as $department) {
            if ($paused) {
                if ($department->queue_paused) {
                    continue;
                }
            } elseif (! $department->lunch_break_paused) {
                continue;
            }

            $department = $transitions->setPaused($department, $paused, null, 'lunch');
            $query = QueueEntry::where('department_id', $department->id)
                ->where('queue_date', today()->toDateString());
            $serving = (clone $query)->where('status', 'serving')->first();
            $next = (clone $query)->where('status', 'waiting')->orderBy('id')->first();
            $waitingCount = (clone $query)->where('status', 'waiting')->count();

            event(new QueueUpdated(
                $department->id,
                $serving?->ticket_number ?? 'Waiting',
                $next?->ticket_number ?? 'Waiting',
                $waitingCount
            ));
        }
    }
}
