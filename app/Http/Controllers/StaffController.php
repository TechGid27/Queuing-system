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
        $current = Cache::get('current_serving_number', '--');

        $nextPerson = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        $next         = $nextPerson ? $nextPerson->ticket_number : '--';
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

        return view('admin.dashboard', compact('currentServing', 'waitingCount', 'completedCount', 'skippedCount', 'waitingStudents'));
    }

    // ─── Queue Actions ────────────────────────────────────────────────────────

    public function callNext()
    {
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
            ->first(['id', 'ticket_number', 'name', 'purpose', 'phone_number']);

        $waitingCount   = $waitingStudents->count();
        $completedCount = QueueEntry::where('status', 'completed')->whereDate('created_at', now()->today())->count();
        $skippedCount   = QueueEntry::where('status', 'no_response')->whereDate('created_at', now()->today())->count();

        return response()->json([
            'waiting'        => $waitingStudents,
            'current'        => $currentServing,
            'waiting_count'  => $waitingCount,
            'completed_count'=> $completedCount,
            'skipped_count'  => $skippedCount,
        ]);
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
