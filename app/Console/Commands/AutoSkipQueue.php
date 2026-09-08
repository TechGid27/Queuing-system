<?php

namespace App\Console\Commands;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Services\QueueTransitionService;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoSkipQueue extends Command
{
    protected $signature = 'queue:auto-skip';

    protected $description = 'Automatically skip unresponsive students after three minutes';

    public function handle(SmsService $sms, QueueTransitionService $transitions): int
    {
        $departments = Department::active()->where('queue_paused', false)->get();

        foreach ($departments as $department) {
            $result = $transitions->autoSkip($department);

            if (! $result) {
                continue;
            }

            // New multi-counter shape: ['skipped' => [...], 'called' => [...]].
            // Keep backward compat with legacy [$skipped, $next] tuple.
            if (isset($result['skipped'])) {
                $skippedList = $result['skipped'];
                $calledList = $result['called'] ?? [];
            } else {
                [$skippedOne, $nextOne] = $result + [null, null];
                $skippedList = $skippedOne ? [$skippedOne] : [];
                $calledList = $nextOne ? [$nextOne] : [];
            }

            if (empty($skippedList)) {
                continue;
            }

            foreach ($skippedList as $skipped) {
                if ($skipped->phone_number) {
                    $sms->sendSkippedNotification($skipped->phone_number, $skipped->ticket_number);
                }
            }

            foreach ($calledList as $nextStudent) {
                Cache::forever("current_serving_number_{$department->id}", $nextStudent->ticket_number);
                if ($nextStudent->phone_number) {
                    $sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
                }
            }

            if (empty($calledList)) {
                Cache::forget("current_serving_number_{$department->id}");
            }

            $upNext = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', today())
                ->where('status', 'waiting')
                ->orderBy('id')
                ->first();
            if ($upNext?->phone_number) {
                $sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
            }

            $waitingCount = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', today())
                ->where('status', 'waiting')
                ->count();

            $firstCalled = $calledList[0] ?? null;
            $skippedTickets = implode(', ', array_map(fn ($s) => $s->ticket_number, $skippedList));

            event(new QueueUpdated(
                $department->id,
                $firstCalled?->ticket_number ?? 'Waiting',
                $upNext?->ticket_number ?? 'Waiting',
                $waitingCount,
                null,
                $skippedList[0]->ticket_number
            ));

            $this->info("{$department->name}: skipped {$skippedTickets}.");
        }

        return Command::SUCCESS;
    }
}
