<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Events\QueueUpdated;
use App\Models\QueueEntry;

class LunchBreakQueue extends Command
{
    protected $signature   = 'queue:lunch-break';
    protected $description = 'Automatically pause the queue at lunch break start and resume at lunch break end (times stored in settings table).';

    public function handle(): int
    {
        // Load configurable times from settings (defaults: 12:00 – 13:30)
        $startTime = DB::table('settings')->where('key', 'lunch_break_start')->value('value') ?? '12:00';
        $endTime   = DB::table('settings')->where('key', 'lunch_break_end')->value('value')   ?? '13:30';

        $now          = now();
        $todayStart   = \Carbon\Carbon::createFromFormat('H:i', $startTime, $now->timezone)->setDateFrom($now);
        $todayEnd     = \Carbon\Carbon::createFromFormat('H:i', $endTime,   $now->timezone)->setDateFrom($now);
        $currentPaused = DB::table('settings')->where('key', 'queue_paused')->value('value') === '1';

        // ── Pause at lunch break start ────────────────────────────────────────
        // Trigger once: when current minute matches start time and queue is NOT already paused.
        if ($now->format('H:i') === $todayStart->format('H:i') && ! $currentPaused) {
            $this->setPaused(true);
            $this->info("Lunch break started at {$startTime}. Queue paused automatically.");
            return Command::SUCCESS;
        }

        // ── Resume at lunch break end ─────────────────────────────────────────
        // Trigger once: when current minute matches end time and queue IS paused.
        if ($now->format('H:i') === $todayEnd->format('H:i') && $currentPaused) {
            $this->setPaused(false);
            $this->info("Lunch break ended at {$endTime}. Queue resumed automatically.");
            return Command::SUCCESS;
        }

        $this->info('No lunch break action needed at ' . $now->format('H:i') . '.');
        return Command::SUCCESS;
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function setPaused(bool $paused): void
    {
        DB::table('settings')
            ->updateOrInsert(
                ['key' => 'queue_paused'],
                ['value' => $paused ? '1' : '0', 'updated_at' => now()]
            );

        // Broadcast so all connected clients (student, TV, staff) update instantly
        $serving = QueueEntry::where('status', 'serving')->first();
        $current = $serving ? $serving->ticket_number : 'Waiting';

        if ($serving) {
            Cache::forever('current_serving_number', $current);
        } else {
            Cache::forget('current_serving_number');
        }

        $nextPerson   = QueueEntry::where('status', 'waiting')->orderBy('id', 'asc')->first();
        $next         = $nextPerson ? $nextPerson->ticket_number : 'Waiting';
        $waitingCount = QueueEntry::where('status', 'waiting')->count();

        event(new QueueUpdated($current, $next, $waitingCount));
    }
}
