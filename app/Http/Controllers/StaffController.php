<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueueEntry;
use App\Events\QueueUpdated;
use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class StaffController extends Controller
{
    private SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function broadcastQueueState(?string $completedTicket = null, ?string $skippedTicket = null): void
    {
        $serving = QueueEntry::where('status', 'serving')->first();
        $current = $serving ? $serving->ticket_number : 'Waiting';

        // Keep cache in sync
        if ($serving) {
            Cache::forever('current_serving_number', $current);
        } else {
            Cache::forget('current_serving_number');
        }

        $nextPerson = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        $next         = $nextPerson ? $nextPerson->ticket_number : 'Waiting';
        $waitingCount = QueueEntry::where('status', 'waiting')->count();

        event(new QueueUpdated($current, $next, $waitingCount, $completedTicket, $skippedTicket));
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

        $queuePaused = DB::table('settings')->where('key', 'queue_paused')->value('value') === '1';

        return view('admin.dashboard', compact('currentServing', 'waitingCount', 'completedCount', 'skippedCount', 'waitingStudents', 'queuePaused'));
    }

    // ─── Queue Pause / Resume ─────────────────────────────────────────────────

    public function togglePause()
    {
        $current = DB::table('settings')->where('key', 'queue_paused')->value('value');
        $newVal  = $current === '1' ? '0' : '1';

        DB::table('settings')
            ->updateOrInsert(
                ['key' => 'queue_paused'],
                ['value' => $newVal, 'updated_at' => now()]
            );

        // Broadcast so student/TV views update instantly
        $this->broadcastQueueState();

        $msg = $newVal === '1' ? 'Queue paused. Students will see a break notice.' : 'Queue resumed.';
        return back()->with('success', $msg);
    }

    // ─── Queue Actions ────────────────────────────────────────────────────────

    public function callNext()
    {
        // Prevent accidental double-calls within 3 seconds (e.g. two staff clicking at once on a single-window setup)
        if (Cache::has('call_next_lock')) {
            return back()->with('warning', 'Please wait before calling the next student.');
        }
        Cache::put('call_next_lock', true, now()->addSeconds(3));

        $completedTicket = null;

        $nextStudent = DB::transaction(function () use (&$completedTicket) {
            // Lock the first waiting student so concurrent requests can't grab the same one
            $next = QueueEntry::where('status', 'waiting')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            // Complete the currently serving student
            $serving = QueueEntry::where('status', 'serving')->lockForUpdate()->first();
            if ($serving) {
                $serving->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
                $completedTicket = $serving->ticket_number;
            }

            // Mark next student as serving
            $next->update([
                'status'    => 'serving',
                'served_at' => now(),
            ]);

            Cache::forever('current_serving_number', $next->ticket_number);

            return $next;
        });

        if (! $nextStudent) {
            return back()->with('warning', 'No Students Waiting');
        }

        // SMS outside transaction (non-critical, can fail without rolling back)
        if ($completedTicket) {
            $prevServing = QueueEntry::where('ticket_number', $completedTicket)->first();
            if ($prevServing?->phone_number) {
                $this->sms->sendCompletedNotification($prevServing->phone_number, $completedTicket);
            }
        }

        if ($nextStudent->phone_number) {
            $this->sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
        }

        $upNext = QueueEntry::where('status', 'waiting')->orderBy('id', 'asc')->first();
        if ($upNext?->phone_number) {
            $this->sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
        }

        $this->broadcastQueueState($completedTicket);

        return back()->with('success', "Now serving: {$nextStudent->ticket_number}");
    }

    public function complete($id)
    {
        $student = QueueEntry::findOrFail($id);
        $student->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        if ($student->phone_number) {
            $this->sms->sendCompletedNotification($student->phone_number, $student->ticket_number);
        }

        // Clear cache if no one else is serving
        if (! QueueEntry::where('status', 'serving')->exists()) {
            Cache::forget('current_serving_number');
        }

        $this->broadcastQueueState($student->ticket_number);

        return back()->with('success', 'Student completed.');
    }

    public function reject($id)
    {
        $student = QueueEntry::findOrFail($id);
        $student->update(['status' => 'no_response', 'completed_at' => now()]);

        if ($student->phone_number) {
            $this->sms->sendSkippedNotification($student->phone_number, $student->ticket_number);
        }

        // Clear cache if no one else is serving
        if (! QueueEntry::where('status', 'serving')->exists()) {
            Cache::forget('current_serving_number');
        }

        $upNext = QueueEntry::where('status', 'waiting')->orderBy('id', 'asc')->first();
        if ($upNext?->phone_number) {
            $this->sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
        }

        $this->broadcastQueueState(null, $student->ticket_number);

        return back()->with('success', 'Student skipped.');
    }

    // ─── API: Waiting List ────────────────────────────────────────────────────

    public function waitingList()
    {
        $waitingStudents = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->get(['id', 'ticket_number', 'name', 'purpose']);

        $currentServing = QueueEntry::where('status', 'serving')
            ->first(['id', 'ticket_number', 'name', 'purpose', 'phone_number', 'served_at', 'updated_at']);

        $waitingCount   = $waitingStudents->count();
        $completedCount = QueueEntry::where('status', 'completed')->whereDate('created_at', now()->today())->count();
        $skippedCount   = QueueEntry::where('status', 'no_response')->whereDate('created_at', now()->today())->count();
        $queuePaused    = DB::table('settings')->where('key', 'queue_paused')->value('value') === '1';
        $avgServeTime   = $this->getAvgServeMinutes();

        return response()->json([
            'waiting'         => $waitingStudents,
            'current'         => $currentServing ? array_merge($currentServing->toArray(), [
                'served_at_ts' => ($currentServing->served_at ?? $currentServing->updated_at)->timestamp,
            ]) : null,
            'waiting_count'   => $waitingCount,
            'completed_count' => $completedCount,
            'skipped_count'   => $skippedCount,
            'queue_paused'    => $queuePaused,
            'avg_serve_mins'  => $avgServeTime,
        ]);
    }

    /**
     * Compute average serve time (minutes) from today's completed entries.
     * Falls back to 5 minutes if not enough data.
     */
    public static function getAvgServeMinutes(): float
    {
        $completed = QueueEntry::where('status', 'completed')
            ->whereDate('created_at', now()->today())
            ->whereNotNull('served_at')
            ->whereNotNull('completed_at')
            ->get(['served_at', 'completed_at']);

        if ($completed->count() < 2) {
            return 5.0; // default fallback
        }

        $totalSeconds = $completed->sum(fn($e) => $e->completed_at->diffInSeconds($e->served_at));
        $avgSeconds   = $totalSeconds / $completed->count();

        // Clamp between 1 and 30 minutes to avoid wild outliers
        return round(max(1, min(30, $avgSeconds / 60)), 1);
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
