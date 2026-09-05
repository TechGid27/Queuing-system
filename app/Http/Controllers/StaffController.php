<?php

namespace App\Http\Controllers;

use App\Events\QueueUpdated;
use App\Models\Department;
use App\Models\QueueEntry;
use App\Services\SmsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    private SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
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
        $serving = (clone $query)->where('status', 'serving')->first();
        $current = $serving?->ticket_number ?? 'Waiting';

        if ($serving) {
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
        $currentServing = (clone $queue)->where('status', 'serving')->first();
        $waitingCount = (clone $queue)->where('status', 'waiting')->count();
        $completedCount = (clone $queue)->where('status', 'completed')->count();
        $skippedCount = (clone $queue)->where('status', 'no_response')->count();
        $waitingStudents = (clone $queue)->where('status', 'waiting')->orderBy('id')->paginate(10)->withQueryString();

        $user = Auth::guard('web')->user();
        $isAdmin = $user->role === 'admin';
        $departments = $isAdmin
            ? Department::orderBy('name')->get()
            : collect([$selectedDepartment])->filter();
        $queuePaused = (bool) $selectedDepartment?->queue_paused;
        $lunchBreakStart = DB::table('settings')->where('key', 'lunch_break_start')->value('value') ?? '12:00';
        $lunchBreakEnd = DB::table('settings')->where('key', 'lunch_break_end')->value('value') ?? '13:30';

        return view('admin.dashboard', compact(
            'currentServing',
            'waitingCount',
            'completedCount',
            'skippedCount',
            'waitingStudents',
            'queuePaused',
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

        $validated = $request->validate([
            'action' => 'nullable|in:pause,resume',
        ]);
        $action = $validated['action'] ?? null;
        $isPaused = match ($action) {
            'pause' => true,
            'resume' => false,
            default => ! $department->queue_paused,
        };

        $department->update([
            'queue_paused' => $isPaused,
            'lunch_break_paused' => false,
        ]);
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

        if (! Cache::add("call_next_lock_{$department->id}", true, 3)) {
            return back()->with('warning', 'Please wait before calling the next student.');
        }

        [$nextStudent, $completedStudent] = DB::transaction(function () use ($department) {
            $serving = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', today())
                ->where('status', 'serving')
                ->lockForUpdate()
                ->first();

            $next = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', today())
                ->where('status', 'waiting')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return [null, null];
            }

            if ($serving) {
                $serving->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            $next->update([
                'status' => 'serving',
                'served_at' => now(),
            ]);

            return [$next, $serving];
        });

        if (! $nextStudent) {
            return back()->with('warning', 'No students waiting in this department.');
        }

        Cache::forever($this->currentCacheKey($department->id), $nextStudent->ticket_number);

        if ($completedStudent?->phone_number) {
            $this->sms->sendCompletedNotification($completedStudent->phone_number, $completedStudent->ticket_number);
        }

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

        $this->broadcastQueueState($department->id, $completedStudent?->ticket_number);

        return back()->with('success', "Now serving {$nextStudent->ticket_number} in {$department->name}.");
    }

    public function complete($id)
    {
        $student = QueueEntry::findOrFail($id);
        $this->authorizeQueueEntry($student);

        if ($student->status !== 'serving') {
            return back()->with('warning', 'Only the currently serving ticket can be completed.');
        }

        $student->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

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

        if ($student->status !== 'serving') {
            return back()->with('warning', 'Only the currently serving ticket can be skipped.');
        }

        $student->update([
            'status' => 'no_response',
            'completed_at' => now(),
        ]);

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
        $currentServing = (clone $queue)
            ->where('status', 'serving')
            ->first(['id', 'ticket_number', 'name', 'purpose', 'phone_number', 'served_at', 'updated_at']);

        return response()->json([
            'department_id' => $department?->id,
            'department_active' => (bool) $department?->is_active,
            'waiting' => $waitingStudents->items(),
            'current' => $currentServing ? array_merge($currentServing->toArray(), [
                'served_at_ts' => ($currentServing->served_at ?? $currentServing->updated_at)->timestamp,
            ]) : null,
            'waiting_count' => (clone $queue)->where('status', 'waiting')->count(),
            'pagination' => [
                'current_page' => $waitingStudents->currentPage(),
                'last_page' => $waitingStudents->lastPage(),
            ],
            'completed_count' => (clone $queue)->where('status', 'completed')->count(),
            'skipped_count' => (clone $queue)->where('status', 'no_response')->count(),
            'queue_paused' => (bool) $department?->queue_paused,
            'pause_source' => $department?->lunch_break_paused ? 'lunch' : 'manual',
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
