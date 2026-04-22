<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueueEntry;
use App\Events\QueueUpdated;
use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class StaffController extends Controller
{
    private SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function broadcastQueueState(): void
    {
        $current = Cache::get('current_serving_number', '--');

        $nextPerson = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        $next         = $nextPerson ? $nextPerson->ticket_number : '--';
        $waitingCount = QueueEntry::where('status', 'waiting')->count();

        event(new QueueUpdated($current, $next, $waitingCount));
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $currentServing = QueueEntry::where('status', 'serving')->first();
        $waitingCount   = QueueEntry::where('status', 'waiting')->count();
        $completedCount = QueueEntry::where('status', 'completed')->whereDate('created_at', now()->today())->count();
        $skippedCount   = QueueEntry::where('status', 'no_response')->whereDate('created_at', now()->today())->count();

        $waitingStudents = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->paginate(10);

        return view('admin.dashboard', compact('currentServing', 'waitingCount', 'completedCount', 'skippedCount', 'waitingStudents'));
    }

    // ─── Queue Actions ────────────────────────────────────────────────────────

    public function callNext()
    {
        $nextStudent = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        if (! $nextStudent) {
            return back()->with('warning', 'No Students Waiting');
        }

        // Complete the currently serving student
        $serving = QueueEntry::where('status', 'serving')->first();
        if ($serving) {
            $serving->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // SMS: notify completed student
            if ($serving->phone_number) {
                $this->sms->sendCompletedNotification($serving->phone_number, $serving->ticket_number);
            }
        }

        // Mark next student as serving
        $nextStudent->update([
            'status'    => 'serving',
            'served_at' => now(),
        ]);

        Cache::forever('current_serving_number', $nextStudent->ticket_number);

        // SMS: notify the student now being served
        if ($nextStudent->phone_number) {
            $this->sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
        }

        // SMS: notify the NEW next-in-line (2nd in queue) to prepare
        // Fix #5: only send if this is a different student from the one just called
        $upNext = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        if ($upNext && $upNext->phone_number && $upNext->id !== $nextStudent->id) {
            $this->sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
        }

        $this->broadcastQueueState();

        return back()->with('success', "Now serving: {$nextStudent->ticket_number}");
    }

    public function complete($id)
    {
        $student = QueueEntry::findOrFail($id);
        $student->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // SMS: notify student their transaction is done
        if ($student->phone_number) {
            $this->sms->sendCompletedNotification($student->phone_number, $student->ticket_number);
        }

        $this->broadcastQueueState();

        return back()->with('success', 'Student completed.');
    }

    public function reject($id)
    {
        $student = QueueEntry::findOrFail($id);
        $student->update(['status' => 'no_response']);

        // SMS: notify student they were skipped
        if ($student->phone_number) {
            $this->sms->sendSkippedNotification($student->phone_number, $student->ticket_number);
        }

        // SMS: notify the new next-in-line to prepare
        // Fix #5: only send if different from the one just skipped
        $upNext = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        if ($upNext && $upNext->phone_number && $upNext->id !== $student->id) {
            $this->sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
        }

        $this->broadcastQueueState();

        return back()->with('success', 'Student skipped.');
    }

    // ─── Reports ─────────────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $entries = QueueEntry::whereDate('created_at', $date)
            ->orderBy('id', 'asc')
            ->get();

        return view('admin.reports', compact('entries', 'date'));
    }

    public function downloadReport(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $entries = QueueEntry::whereDate('created_at', $date)
            ->orderBy('id', 'asc')
            ->get();

        $pdf = Pdf::loadView('admin.report_pdf', compact('entries', 'date'));
        return $pdf->download("Queue-Report-{$date}.pdf");
    }
}
