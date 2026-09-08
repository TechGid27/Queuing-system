<?php

namespace App\Http\Controllers;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Services\QueueTransitionService;
use App\Services\SmsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    private SmsService $sms;
    private QueueTransitionService $transitions;

    public function __construct(SmsService $sms, QueueTransitionService $transitions)
    {
        $this->sms = $sms;
        $this->transitions = $transitions;
    }

    private function resolveDepartment(Request $request): ?Department
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return null;
        }

        if ($user->role === 'staff') {
            return $user->department;
        }

        $hasRequestedDepartment = $request->filled('department_id');
        $departmentId = $hasRequestedDepartment
            ? $request->integer('department_id')
            : (int) $request->session()->get('admin_department_id');
        $department = $departmentId ? Department::find($departmentId) : null;

        if ($hasRequestedDepartment && ! $department) {
            return null;
        }

        $department ??= Department::active()->orderBy('name')->first();
        $department ??= Department::orderBy('name')->first();

        if ($department) {
            $request->session()->put('admin_department_id', $department->id);
        }

        return $department;
    }

    private function todayQueue(?Department $department)
    {
        $query = QueueEntry::query()->whereDate('queue_date', today());

        return $department
            ? $query->where('department_id', $department->id)
            : $query->whereRaw('1 = 0');
    }

    private function currentCacheKey(int $departmentId): string
    {
        return "current_serving_number_{$departmentId}";
    }

    private function authorizeQueueEntry(QueueEntry $entry): void
    {
        $user = Auth::guard('web')->user();
        abort_unless($user && $user->is_active, 403);
        abort_unless($entry->queue_date?->isToday(), 404);
        abort_unless($entry->department?->is_active, 403);

        if ($user->role === 'staff') {
            abort_unless((int) $user->department_id === (int) $entry->department_id, 403);
        } else {
            abort_unless($user->role === 'admin', 403);
        }
    }

    private function broadcastQueueState(
        int $departmentId,
        ?string $completedTicket = null,
        ?string $skippedTicket = null
    ): void {
        $query = QueueEntry::where('department_id', $departmentId)
            ->whereDate('queue_date', today());
        $servingCount = (clone $query)->where('status', 'serving')->count();
        $firstServing = (clone $query)->where('status', 'serving')->orderBy('id')->first();
        $current = $firstServing?->ticket_number ?? 'Waiting';

        if ($servingCount > 0) {
            Cache::forever($this->currentCacheKey($departmentId), $current);
        } else {
            Cache::forget($this->currentCacheKey($departmentId));
        }

        $nextPerson = (clone $query)->where('status', 'waiting')->orderBy('id')->first();
        $waitingCount = (clone $query)->where('status', 'waiting')->count();

        event(new QueueUpdated(
            $departmentId,
            $current,
            $nextPerson?->ticket_number ?? 'Waiting',
            $waitingCount,
            $completedTicket,
            $skippedTicket
        ));
    }

    public function index(Request $request)
    {
        $selectedDepartment = $this->resolveDepartment($request);
        $queue = $this->todayQueue($selectedDepartment);
        $currentServings = (clone $queue)
            ->where('status', 'serving')
            ->with(['counter', 'servedBy'])
            ->orderBy('id')
            ->get();
        // Backward compat for views still expecting a single $currentServing.
        $currentServing = $currentServings->first();
        $servingCount = $currentServings->count();
        $waitingCount = (clone $queue)->where('status', 'waiting')->count();
        $completedCount = (clone $queue)->where('status', 'completed')->count();
        $skippedCount = (clone $queue)->where('status', 'no_response')->count();
        $waitingStudents = (clone $queue)->where('status', 'waiting')->orderBy('id')->paginate(10)->withQueryString();
        $counters = $selectedDepartment
            ? \App\Models\Counter::where('department_id', $selectedDepartment->id)->orderBy('id')->get()
            : collect();
        // Backward compat: legacy serving rows without counter_id display on the first active counter.
        $firstCounter = $counters->firstWhere('is_active', true) ?? $counters->first();
        if ($firstCounter) {
            foreach ($currentServings as $serving) {
                if (! $serving->counter_id) {
                    $serving->setRelation('counter', $firstCounter);
                    $serving->counter_id = $firstCounter->id;
                }
            }
        }
        $busyCounterIds = $currentServings->pluck('counter_id')->filter()->all();

        $user = Auth::guard('web')->user();
        $myServing = ($user && $user->role === 'staff') ? $currentServings->firstWhere('served_by', $user->id) : null;
        $isAdmin = $user->role === 'admin';
        $departments = $isAdmin
            ? Department::orderBy('name')->get()
            : collect([$selectedDepartment])->filter();
        $queuePaused = (bool) $selectedDepartment?->queue_paused;
        $autoPauseEnabled = (bool) ($selectedDepartment?->auto_pause_enabled ?? true);
        $lunchBreakStart = DB::table('settings')->where('key', 'lunch_break_start')->value('value') ?? '12:00';
        $lunchBreakEnd = DB::table('settings')->where('key', 'lunch_break_end')->value('value') ?? '13:30';
        $servingCount = $currentServings->count();

        return view('admin.dashboard', compact(
            'currentServing',
            'currentServings',
            'servingCount',
            'counters',
            'busyCounterIds',
            'myServing',
            'waitingCount',
            'completedCount',
            'skippedCount',
            'waitingStudents',
            'queuePaused',
            'autoPauseEnabled',
            'lunchBreakStart',
            'lunchBreakEnd',
            'selectedDepartment',
            'departments',
            'isAdmin'
        ));
    }

    public function togglePause(Request $request)
    {
        $department = $this->resolveDepartment($request);
        abort_unless($department, 404);

        if (! $department->is_active) {
            $message = 'Activate this department before changing its queue status.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('warning', $message);
        }

        if ((bool) $department->auto_pause_enabled) {
            $message = 'Switch to Manual mode to use Pause / Resume. This department is on Automatic (lunch break) mode.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('warning', $message);
        }

        $validated = $request->validate([
            'action' => 'nullable|in:pause,resume',
        ]);
        $action = $validated['action'] ?? null;
        $isPaused = match ($action) {
            'pause' => true,
            'resume' => false,
            default => ! $department->queue_paused,
        };

        try {
            $department = $this->transitions->setPaused(
                $department,
                $isPaused,
                Auth::guard('web')->user(),
                'manual'
            );
        } catch (\LogicException $exception) {
            $message = $exception->getMessage();

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('warning', $message);
        }
        $this->broadcastQueueState($department->id);

        $message = $isPaused
            ? "{$department->name} queue paused."
            : "{$department->name} queue resumed.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'department_id' => $department->id,
                'queue_paused' => $isPaused,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function updatePauseMode(Request $request)
    {
        $department = $this->resolveDepartment($request);
        abort_unless($department, 404);

        $validated = $request->validate([
            'mode' => 'required|in:auto,manual',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        $autoEnabled = $validated['mode'] === 'auto';
        $department->update(['auto_pause_enabled' => $autoEnabled]);

        // Switching to Manual during a lunch auto-pause converts it to a manual
        // pause so staff can resume it. Switching to Auto keeps current state
        // and lets the lunch scheduler take over from here.
        if (! $autoEnabled && $department->lunch_break_paused) {
            $department->update(['lunch_break_paused' => false]);
            $department->refresh();
        }

        $this->broadcastQueueState($department->id);

        $message = $autoEnabled
            ? "{$department->name} set to Automatic (lunch break) mode."
            : "{$department->name} set to Manual mode.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'department_id' => $department->id,
                'auto_pause_enabled' => $autoEnabled,
                'pause_mode' => $validated['mode'],
                'queue_paused' => (bool) $department->queue_paused,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function callNext(Request $request)
    {
        $department = $this->resolveDepartment($request);
        abort_unless($department, 404);

        if (! $department->is_active) {
            return back()->with('warning', 'Activate this department before calling the next student.');
        }

        if ($department->queue_paused) {
            return back()->with('warning', 'Resume this department queue before calling the next student.');
        }

        $validated = $request->validate([
            'counter_id' => 'nullable|integer|exists:counters,id',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        $user = Auth::guard('web')->user();
        if ($user && $user->role === 'staff') {
            $alreadyServing = $this->todayQueue($department)
                ->where('status', 'serving')
                ->where('served_by', $user->id)
                ->exists();

            if ($alreadyServing) {
                return back()->with('warning', 'Complete or skip your current ticket before calling the next student.');
            }
        }

        try {
            $nextStudent = $this->transitions->callNext($department, $user, $validated['counter_id'] ?? null);
        } catch (\LogicException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        Cache::forever($this->currentCacheKey($department->id), $nextStudent->ticket_number);

        if ($nextStudent->phone_number) {
            $this->sms->sendNowServingNotification($nextStudent->phone_number, $nextStudent->ticket_number);
        }

        $upNext = QueueEntry::where('department_id', $department->id)
            ->whereDate('queue_date', today())
            ->where('status', 'waiting')
            ->orderBy('id')
            ->first();
        if ($upNext?->phone_number) {
            $this->sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
        }

        $this->broadcastQueueState($department->id);

        $counterSuffix = $nextStudent->counter ? " at {$nextStudent->counter->name}" : '';

        return back()->with('success', "Now serving {$nextStudent->ticket_number} in {$department->name}{$counterSuffix}.");
    }

    public function complete($id)
    {
        $student = QueueEntry::findOrFail($id);
        $this->authorizeQueueEntry($student);

        try {
            $student = $this->transitions->complete($student, Auth::guard('web')->user());
        } catch (\LogicException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        if ($student->phone_number) {
            $this->sms->sendCompletedNotification($student->phone_number, $student->ticket_number);
        }

        Cache::forget($this->currentCacheKey($student->department_id));
        $this->broadcastQueueState($student->department_id, $student->ticket_number);

        return back()->with('success', 'Student completed.');
    }

    public function reject($id)
    {
        $student = QueueEntry::findOrFail($id);
        $this->authorizeQueueEntry($student);

        try {
            $student = $this->transitions->skip($student, Auth::guard('web')->user());
        } catch (\LogicException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        if ($student->phone_number) {
            $this->sms->sendSkippedNotification($student->phone_number, $student->ticket_number);
        }

        Cache::forget($this->currentCacheKey($student->department_id));

        $upNext = QueueEntry::where('department_id', $student->department_id)
            ->whereDate('queue_date', today())
            ->where('status', 'waiting')
            ->orderBy('id')
            ->first();
        if ($upNext?->phone_number) {
            $this->sms->sendAlmostYourTurnNotification($upNext->phone_number, $upNext->ticket_number);
        }

        $this->broadcastQueueState($student->department_id, null, $student->ticket_number);

        return back()->with('success', 'Student skipped.');
    }

    public function waitingList(Request $request)
    {
        $department = $this->resolveDepartment($request);
        $queue = $this->todayQueue($department);
        $waitingStudents = (clone $queue)
            ->where('status', 'waiting')
            ->orderBy('id')
            ->paginate(10, ['id', 'ticket_number', 'name', 'purpose']);
        $currentServings = (clone $queue)
            ->where('status', 'serving')
            ->with(['counter', 'servedBy'])
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => array_merge($s->toArray(), [
                'served_at_ts' => (($s->served_at ?? $s->updated_at)->timestamp ?? null),
                'counter_name' => $s->counter?->name,
                'served_by_name' => $s->servedBy?->name,
            ]));
        $currentServing = $currentServings->first();
        $counters = $department
            ? \App\Models\Counter::where('department_id', $department->id)->orderBy('id')->get(['id', 'name', 'is_active'])
            : collect();
        $busyCounterIds = (clone $queue)->where('status', 'serving')->whereNotNull('counter_id')->pluck('counter_id')->all();

        return response()->json([
            'department_id' => $department?->id,
            'department_active' => (bool) $department?->is_active,
            'waiting' => $waitingStudents->items(),
            'current' => $currentServing,
            'currents' => $currentServings->values(),
            'serving_count' => $currentServings->count(),
            'counters' => $counters,
            'busy_counter_ids' => $busyCounterIds,
            'waiting_count' => (clone $queue)->where('status', 'waiting')->count(),
            'pagination' => [
                'current_page' => $waitingStudents->currentPage(),
                'last_page' => $waitingStudents->lastPage(),
            ],
            'completed_count' => (clone $queue)->where('status', 'completed')->count(),
            'skipped_count' => (clone $queue)->where('status', 'no_response')->count(),
            'queue_paused' => (bool) $department?->queue_paused,
            'pause_source' => $department?->lunch_break_paused ? 'lunch' : 'manual',
            'auto_pause_enabled' => (bool) ($department?->auto_pause_enabled ?? true),
            'pause_mode' => ($department?->auto_pause_enabled ?? true) ? 'auto' : 'manual',
            'avg_serve_mins' => self::getAvgServeMinutes($department?->id),
            'lunch_break_start' => DB::table('settings')->where('key', 'lunch_break_start')->value('value') ?? '12:00',
            'lunch_break_end' => DB::table('settings')->where('key', 'lunch_break_end')->value('value') ?? '13:30',
        ]);
    }

    public static function getAvgServeMinutes(?int $departmentId = null): float
    {
        if (! $departmentId) {
            return 5.0;
        }

        $completed = QueueEntry::where('department_id', $departmentId)
            ->whereDate('queue_date', today())
            ->where('status', 'completed')
            ->whereNotNull('served_at')
            ->whereNotNull('completed_at')
            ->get(['served_at', 'completed_at']);

        if ($completed->count() < 2) {
            return 5.0;
        }

        $totalSeconds = $completed->sum(fn ($entry) => $entry->completed_at->diffInSeconds($entry->served_at));

        return round(max(1, min(30, ($totalSeconds / $completed->count()) / 60)), 1);
    }

    public function reports(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);
        $date = $validated['date'] ?? now()->format('Y-m-d');
        $selectedDepartment = $this->resolveDepartment($request);
        $entries = $selectedDepartment
            ? QueueEntry::where('department_id', $selectedDepartment->id)->whereDate('queue_date', $date)->orderBy('id')->get()
            : collect();
        $isAdmin = Auth::guard('web')->user()->role === 'admin';
        $departments = $isAdmin ? Department::orderBy('name')->get() : collect([$selectedDepartment])->filter();

        return view('admin.reports', compact('entries', 'date', 'selectedDepartment', 'departments', 'isAdmin'));
    }

    public function downloadReport(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);
        $date = $validated['date'] ?? now()->format('Y-m-d');
        $selectedDepartment = $this->resolveDepartment($request);
        $entries = $selectedDepartment
            ? QueueEntry::where('department_id', $selectedDepartment->id)->whereDate('queue_date', $date)->orderBy('id')->get()
            : collect();
        $departmentName = $selectedDepartment?->name ?? 'Department';

        $pdf = Pdf::loadView('admin.report_pdf', compact('entries', 'date', 'selectedDepartment'));

        return $pdf->download("Queue-Report-{$departmentName}-{$date}.pdf");
    }
}
