<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QueueEntry;
use App\Events\QueueUpdated;
use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AutoSkipQueue extends Command
{
    protected $signature = 'queue:auto-skip';
    protected $description = 'Automatically skip students in serving status for more than 3 minutes';

    public function handle(SmsService $sms)
    {
        // Don't auto-skip while queue is paused (e.g. lunch break)
        $paused = DB::table('settings')->where('key', 'queue_paused')->value('value');
        if ($paused === '1') {
            $this->info('Queue is paused. Skipping auto-skip check.');
            return Command::SUCCESS;
        }

        $servingStudents = QueueEntry::where('status', 'serving')
            ->where(function ($q) {
                // Use served_at if available, fall back to updated_at
                $q->where('served_at', '<', now()->subMinutes(3))
                  ->orWhere(function ($q2) {
                      $q2->whereNull('served_at')
                         ->where('updated_at', '<', now()->subMinutes(3));
                  });
            })
            ->get();

        foreach ($servingStudents as $student) {
            $student->update(['status' => 'no_response', 'completed_at' => now()]);
            $this->info("Student {$student->ticket_number} auto-skipped due to inactivity.");

            if ($student->phone_number) {
                $sms->sendSkippedNotification($student->phone_number, $student->ticket_number);
            }

            $skippedTicket = $student->ticket_number;
            $nextStudent = QueueEntry::where('status', 'waiting')
                ->orderBy('id', 'asc')
                ->first();

            if ($nextStudent) {
                $nextStudent->update([
                    'status'    => 'serving',
                    'served_at' => now(),
                ]);

                Cache::forever('current_serving_number', $nextStudent->ticket_number);
                $this->info("Automatically called next student: {$nextStudent->ticket_number}");

                if ($nextStudent->phone_number) {
                    $sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
                }

                $upNext = QueueEntry::where('status', 'waiting')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($upNext?->phone_number) {
                    $sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
                }
            } else {
                Cache::forget('current_serving_number');
                $this->info('Queue is now empty.');
            }

            $current      = Cache::get('current_serving_number', 'Waiting');
            $nextPerson   = QueueEntry::where('status', 'waiting')->orderBy('id', 'asc')->first();
            $next         = $nextPerson ? $nextPerson->ticket_number : 'Waiting';
            $waitingCount = QueueEntry::where('status', 'waiting')->count();

            event(new QueueUpdated($current, $next, $waitingCount, null, $skippedTicket));
        }

        return Command::SUCCESS;
    }
}
