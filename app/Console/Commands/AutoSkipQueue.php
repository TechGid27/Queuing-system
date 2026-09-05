<?php

namespace App\Console\Commands;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AutoSkipQueue extends Command
{
    protected $signature = 'queue:auto-skip';

    protected $description = 'Automatically skip unresponsive students after three minutes';

    public function handle(SmsService $sms): int
    {
        $departments = Department::active()->where('queue_paused', false)->get();

        foreach ($departments as $department) {
            [$skipped, $nextStudent] = DB::transaction(function () use ($department) {
                $serving = QueueEntry::where('department_id', $department->id)
                    ->whereDate('queue_date', today())
                    ->where('status', 'serving')
                    ->where(function ($query) {
                        $query->where('served_at', '<', now()->subMinutes(3))
                            ->orWhere(function ($fallback) {
                                $fallback->whereNull('served_at')
                                    ->where('updated_at', '<', now()->subMinutes(3));
                            });
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $serving) {
                    return [null, null];
                }

                $serving->update([
                    'status' => 'no_response',
                    'completed_at' => now(),
                ]);

                $next = QueueEntry::where('department_id', $department->id)
                    ->whereDate('queue_date', today())
                    ->where('status', 'waiting')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($next) {
                    $next->update([
                        'status' => 'serving',
                        'served_at' => now(),
                    ]);
                }

                return [$serving, $next];
            });

            if (! $skipped) {
                continue;
            }

            if ($skipped->phone_number) {
                $sms->sendSkippedNotification($skipped->phone_number, $skipped->ticket_number);
            }

            if ($nextStudent) {
                Cache::forever("current_serving_number_{$department->id}", $nextStudent->ticket_number);
                if ($nextStudent->phone_number) {
                    $sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
                }
            } else {
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

            event(new QueueUpdated(
                $department->id,
                $nextStudent?->ticket_number ?? 'Waiting',
                $upNext?->ticket_number ?? 'Waiting',
                $waitingCount,
                null,
                $skipped->ticket_number
            ));

            $this->info("{$department->name}: skipped {$skipped->ticket_number}.");
        }

        return Command::SUCCESS;
    }
}
