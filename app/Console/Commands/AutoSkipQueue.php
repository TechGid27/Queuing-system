<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QueueEntry;
use App\Events\QueueUpdated;
use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;

class AutoSkipQueue extends Command
{
    protected $signature = 'queue:auto-skip';
    protected $description = 'Automatically skip students in serving status for more than 3 minutes';

    public function handle(SmsService $sms)
    {
        $servingStudents = QueueEntry::where('status', 'serving')
            ->where('updated_at', '<', now()->subMinutes(3))
            ->get();

        foreach ($servingStudents as $student) {
            $student->update(['status' => 'no_response']);
            $this->info("Student {$student->ticket_number} auto-skipped due to inactivity.");

            // SMS: notify skipped student
            if ($student->phone_number) {
                $sms->sendSkippedNotification($student->phone_number, $student->ticket_number);
            }

            // Call next waiting student
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

                // SMS: notify student now being served
                if ($nextStudent->phone_number) {
                    $sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
                }

                // SMS: notify the new next-in-line to prepare (fix #5: different from just-called)
                $upNext = QueueEntry::where('status', 'waiting')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($upNext && $upNext->phone_number && $upNext->id !== $nextStudent->id) {
                    $sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
                }
            } else {
                Cache::forget('current_serving_number');
                $this->info('Queue is now empty.');
            }

            // Broadcast updated state
            $current      = Cache::get('current_serving_number', '--');
            $nextPerson   = QueueEntry::where('status', 'waiting')->orderBy('id', 'asc')->first();
            $next         = $nextPerson ? $nextPerson->ticket_number : '--';
            $waitingCount = QueueEntry::where('status', 'waiting')->count();

            event(new QueueUpdated($current, $next, $waitingCount));
        }

        return Command::SUCCESS;
    }
}
