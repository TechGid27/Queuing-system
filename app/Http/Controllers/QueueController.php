<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueueEntry;
use App\Events\QueueUpdated;
use App\Http\Requests\StoreQueueRequest;
use Illuminate\Support\Facades\Cache;

class QueueController extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Broadcast the current queue state to all connected clients.
     */
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

    // ─── Index (Public + Student View) ────────────────────────────────────────

    public function index()
    {
        // Fallback: if cache is empty, try to get from DB
        $currentNumber = Cache::get('current_serving_number');

        if (! $currentNumber) {
            $serving = QueueEntry::where('status', 'serving')->first();
            $currentNumber = $serving ? $serving->ticket_number : '--';
            if ($serving) {
                Cache::forever('current_serving_number', $currentNumber);
            }
        }

        $nextPerson = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        $nextNumber = $nextPerson ? $nextPerson->ticket_number : '--';
        $waitingCount = QueueEntry::where('status', 'waiting')->count();

        $myTicket = null;
        if (auth()->check()) {
            $myTicket = QueueEntry::where('user_id', auth()->id())
                ->whereIn('status', ['waiting', 'serving'])
                ->latest()
                ->first();
        }

        $purposes = \App\Models\Purpose::where('is_active', true)->orderBy('name', 'asc')->get();

        if (auth()->check()) {
            // Staff should never see the student queue page
            if (auth()->user()->role === 'staff') {
                return redirect()->route('admin.index');
            }
            return view('student.index', compact('currentNumber', 'nextNumber', 'waitingCount', 'purposes', 'myTicket'));
        } else {
            return view('dashboard', compact('currentNumber', 'nextNumber', 'waitingCount', 'purposes'));
        }
    }

    // ─── API: Get Status (for polling fallback) ──────────────────────────────

    public function getStatus()
    {
        $current = Cache::get('current_serving_number', '--');

        $nextPerson = QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        $next = $nextPerson ? $nextPerson->ticket_number : '--';

        return response()->json([
            'current' => $current,
            'next'    => $next,
        ]);
    }

    // ─── Store (Join Queue) ───────────────────────────────────────────────────

    public function store(StoreQueueRequest $request)
    {
        $user = auth()->user();

        // Generate daily ticket sequence: ACLC-20260420-001
        $today     = now()->format('Ymd');
        $lastEntry = QueueEntry::where('ticket_number', 'LIKE', "ACLC-{$today}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastEntry) {
            $parts    = explode('-', $lastEntry->ticket_number);
            $sequence = intval(end($parts)) + 1;
        }

        $ticketNumber = "ACLC-{$today}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);

        $purpose = \App\Models\Purpose::find($request->purpose_id);

        $entry = QueueEntry::create([
            'ticket_number' => $ticketNumber,
            'name'          => $user->name,
            'purpose'       => $purpose ? $purpose->name : 'Unknown',
            'purpose_id'    => $request->purpose_id,
            'phone_number'  => $user->phone_number,
            'status'        => 'waiting',
            'user_id'       => $user->id,
        ]);

        $this->broadcastQueueState();

        return back()
            ->with('success', 'You have successfully joined the queue!')
            ->with('my_number', $ticketNumber);
    }
}
