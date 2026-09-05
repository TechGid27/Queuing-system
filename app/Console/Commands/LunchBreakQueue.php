<?php

namespace App\Console\Commands;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LunchBreakQueue extends Command
{
    protected $signature = 'queue:lunch-break';

    protected $description = 'Automatically pause and resume active department queues at lunch';

    public function handle(): int
    {
        $startTime = DB::table('settings')->where('key', 'lunch_break_start')->value('value') ?? '12:00';
        $endTime = DB::table('settings')->where('key', 'lunch_break_end')->value('value') ?? '13:30';
        $now = now();
        $todayStart = Carbon::createFromFormat('H:i', $startTime, $now->timezone)->setDateFrom($now);
        $todayEnd = Carbon::createFromFormat('H:i', $endTime, $now->timezone)->setDateFrom($now);

        if ($now->format('H:i') === $todayStart->format('H:i')) {
            $this->setPaused(true);
            $this->info("Active department queues paused at {$startTime}.");

            return Command::SUCCESS;
        }

        if ($now->format('H:i') === $todayEnd->format('H:i')) {
            $this->setPaused(false);
            $this->info("Active department queues resumed at {$endTime}.");

            return Command::SUCCESS;
        }

        $this->info('No lunch break action needed at '.$now->format('H:i').'.');

        return Command::SUCCESS;
    }

    private function setPaused(bool $paused): void
    {
        $departments = Department::active()->get();

        foreach ($departments as $department) {
            if ($paused) {
                if ($department->queue_paused) {
                    continue;
                }
            } elseif (! $department->lunch_break_paused) {
                continue;
            }

            $department->update([
                'queue_paused' => $paused,
                'lunch_break_paused' => $paused,
            ]);
            $query = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', today());
            $serving = (clone $query)->where('status', 'serving')->first();
            $next = (clone $query)->where('status', 'waiting')->orderBy('id')->first();
            $waitingCount = (clone $query)->where('status', 'waiting')->count();

            if ($serving) {
                Cache::forever("current_serving_number_{$department->id}", $serving->ticket_number);
            } else {
                Cache::forget("current_serving_number_{$department->id}");
            }

            event(new QueueUpdated(
                $department->id,
                $serving?->ticket_number ?? 'Waiting',
                $next?->ticket_number ?? 'Waiting',
                $waitingCount
            ));
        }
    }
}
