<?php

namespace App\Http\Controllers;

use App\Events\QueueUpdated;
use App\Http\Requests\StoreQueueRequest;
use App\Models\Department;
use App\Models\QueueEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueController extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Broadcast the current queue state to all connected clients.
     */
    private function currentCacheKey(int $departmentId): string
    {
        return "current_serving_number_{$departmentId}";
    }

    private function resolveDepartment(Request $request, ?QueueEntry $ticket = null): ?Department
    {
        if ($ticket?->department_id) {
            return Department::find($ticket->department_id);
        }

        if ($request->integer('department_id')) {
            return Department::active()->find($request->integer('department_id'));
        }

        return Department::active()->orderBy('name')->first();
    }

    private function queueState(?Department $department): array
    {
        if (! $department) {
            return [
                'currentNumber' => '--',
                'currentServing' => null,
                'nextNumber' => 'Waiting',
                'waitingCount' => 0,
                'waitingList' => collect(),
            ];
        }

        $baseQuery = QueueEntry::where('department_id', $department->id)
            ->whereDate('queue_date', today());

        $currentServing = (clone $baseQuery)->where('status', 'serving')->first();
        $currentNumber = $currentServing?->ticket_number ?? '--';

        if ($currentServing) {
            Cache::forever($this->currentCacheKey($department->id), $currentNumber);
        } else {
            Cache::forget($this->currentCacheKey($department->id));
        }

        $nextPerson = (clone $baseQuery)->where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->first();

        return [
            'currentNumber' => $currentNumber,
            'currentServing' => $currentServing,
            'nextNumber' => $nextPerson?->ticket_number ?? 'Waiting',
            'waitingCount' => (clone $baseQuery)->where('status', 'waiting')->count(),
            'waitingList' => (clone $baseQuery)->where('status', 'waiting')->orderBy('id')->take(8)->get(),
        ];
    }

    private function broadcastQueueState(int $departmentId): void
    {
        $department = Department::findOrFail($departmentId);
        $state = $this->queueState($department);

        event(new QueueUpdated(
            $departmentId,
            $state['currentNumber'],
            $state['nextNumber'],
            $state['waitingCount']
        ));
    }

    // ─── TV Display ──────────────────────────────────────────────────────────────

    public function tv(Request $request)
    {
        $departments = Department::active()->orderBy('name')->get();
        $selectedDepartment = $this->resolveDepartment($request);
        $state = $this->queueState($selectedDepartment);
        extract($state);
        $queuePaused = (bool) $selectedDepartment?->queue_paused;
        $avgServeTime = StaffController::getAvgServeMinutes($selectedDepartment?->id);

        return view('tv', compact('currentNumber', 'currentServing', 'nextNumber', 'waitingCount', 'waitingList', 'queuePaused', 'avgServeTime', 'departments', 'selectedDepartment'));
    }

    // ─── Index (Public + Student View) ────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();
        $guest = Auth::guard('student')->user();

        if ($user && in_array($user->role, ['admin', 'staff'], true)) {
            return redirect()->route('admin.index');
        }

        $myTicket = null;
        $myPosition = null;
        if ($guest) {
            Auth::shouldUse('student');
            $myTicket = QueueEntry::where('guest_id', $guest->id)
                ->whereDate('queue_date', today())
                ->whereIn('status', ['waiting', 'serving'])
                ->latest()
                ->first();
        }

        $departments = Department::active()->orderBy('name')->get();
        $selectedDepartment = $this->resolveDepartment($request, $myTicket);
        $state = $this->queueState($selectedDepartment);
        extract($state);

        if ($myTicket && $myTicket->status === 'waiting') {
            $myPosition = QueueEntry::where('department_id', $myTicket->department_id)
                ->whereDate('queue_date', today())
                ->where('status', 'waiting')
                    ->where('id', '<=', $myTicket->id)
                    ->count();
        }

        $purposes = \App\Models\Purpose::where('is_active', true)->orderBy('name', 'asc')->get();
        $queuePaused = (bool) $selectedDepartment?->queue_paused;
        $avgServeTime = \App\Http\Controllers\StaffController::getAvgServeMinutes($selectedDepartment?->id);

        if ($guest) {
            return view('student.index', compact('currentNumber', 'nextNumber', 'waitingCount', 'purposes', 'myTicket', 'myPosition', 'queuePaused', 'avgServeTime', 'departments', 'selectedDepartment'));
        }

        return view('dashboard', compact('currentNumber', 'nextNumber', 'waitingCount', 'purposes', 'queuePaused', 'avgServeTime', 'departments', 'selectedDepartment'));
    }

    // ─── API: Get Status (for polling fallback) ──────────────────────────────

    public function getStatus(Request $request)
    {
        $guest = Auth::guard('student')->user();
        $departmentId = $request->integer('department_id');
        $selectedDepartment = $departmentId
            ? Department::active()->find($departmentId)
            : Department::active()->orderBy('name')->first();

        if (! $selectedDepartment && $guest && $departmentId) {
            $ownsActiveTicket = QueueEntry::where('guest_id', $guest->id)
                ->where('department_id', $departmentId)
                ->whereDate('queue_date', today())
                ->whereIn('status', ['waiting', 'serving'])
                ->exists();

            if ($ownsActiveTicket) {
                $selectedDepartment = Department::find($departmentId);
            }
        }

        $state = $this->queueState($selectedDepartment);
        $myTicket = null;

        if ($guest && $selectedDepartment) {
            $ticket = QueueEntry::where('guest_id', $guest->id)
                ->where('department_id', $selectedDepartment->id)
                ->whereDate('queue_date', today())
                ->latest()
                ->first(['id', 'ticket_number', 'status']);

            if ($ticket) {
                $position = $ticket->status === 'waiting'
                    ? QueueEntry::where('department_id', $selectedDepartment->id)
                        ->whereDate('queue_date', today())
                        ->where('status', 'waiting')
                        ->where('id', '<=', $ticket->id)
                        ->count()
                    : null;

                $myTicket = [
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                    'position' => $position,
                ];
            }
        }

        return response()->json([
            'department_id' => $selectedDepartment?->id,
            'department_name' => $selectedDepartment?->name,
            'current' => $state['currentNumber'],
            'next' => $state['nextNumber'],
            'waiting_count' => $state['waitingCount'],
            'waiting_list' => $state['waitingList']->map(fn ($entry, $index) => [
                'ticket_number' => $entry->ticket_number,
                'position' => $index + 1,
            ])->values(),
            'current_serving' => $state['currentServing'] ? [
                'ticket_number' => $state['currentServing']->ticket_number,
            ] : null,
            'my_ticket' => $myTicket,
            'queue_paused' => (bool) $selectedDepartment?->queue_paused,
            'avg_serve_mins' => \App\Http\Controllers\StaffController::getAvgServeMinutes($selectedDepartment?->id),
        ]);
    }

    // ─── Store (Join Queue) ───────────────────────────────────────────────────

    public function store(StoreQueueRequest $request)
    {
        $guest = Auth::guard('student')->user();

        abort_unless($guest && $guest->isPhoneVerified(), 403);

        $departmentId = $request->integer('department_id');
        $today = today()->toDateString();

        // Resolve purpose name — handle "Other" free-text option
        if ($request->purpose_id === 'other') {
            $purposeName = trim($request->other_purpose) ?: 'Other';
            $purposeId = null;
        } else {
            $purpose = \App\Models\Purpose::find($request->purpose_id);
            $purposeName = $purpose ? $purpose->name : 'Unknown';
            $purposeId = $request->purpose_id;
        }

        $maxCapacity = (int) config('queue_system.max_capacity', 50);
        $queueEntry = DB::transaction(function () use ($departmentId, $guest, $maxCapacity, $purposeId, $purposeName, $today) {
            $lockedGuest = $guest->newQuery()->whereKey($guest->id)->lockForUpdate()->firstOrFail();
            $department = Department::whereKey($departmentId)->lockForUpdate()->first();

            if (! $department?->is_active) {
                throw ValidationException::withMessages([
                    'department_id' => 'Please select an active department.',
                ]);
            }

            if ($department->queue_paused) {
                throw ValidationException::withMessages([
                    'department_id' => 'This department queue is currently paused.',
                ]);
            }

            $hasActiveTicket = QueueEntry::where('guest_id', $lockedGuest->id)
                ->whereDate('queue_date', $today)
                ->whereIn('status', ['waiting', 'serving'])
                ->exists();

            if ($hasActiveTicket) {
                throw ValidationException::withMessages([
                    'purpose_id' => 'You already have an active ticket in the queue.',
                ]);
            }

            $departmentQueue = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', $today);
            $currentCount = (clone $departmentQueue)
                ->whereIn('status', ['waiting', 'serving'])
                ->count();

            if ($maxCapacity > 0 && $currentCount >= $maxCapacity) {
                throw ValidationException::withMessages([
                    'purpose_id' => "The queue is currently full (max {$maxCapacity} students). Please try again later.",
                ]);
            }

            $sequence = (clone $departmentQueue)
                ->pluck('ticket_number')
                ->reduce(fn (int $max, string $number) => max($max, (int) $number), 0) + 1;

            return QueueEntry::create([
                'ticket_number' => str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'name' => $lockedGuest->student_id ?: 'Guest',
                'purpose' => $purposeName,
                'purpose_id' => $purposeId,
                'phone_number' => $lockedGuest->phone_number,
                'status' => 'waiting',
                'guest_id' => $lockedGuest->id,
                'department_id' => $department->id,
                'queue_date' => $today,
            ]);
        });

        $this->broadcastQueueState($departmentId);

        return back()
            ->with('success', 'You have successfully joined the queue!')
            ->with('my_number', $queueEntry->ticket_number);
    }
}
