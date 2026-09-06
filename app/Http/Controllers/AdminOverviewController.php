<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\QueueAction;
use App\Models\QueueEntry;
use App\Models\SmsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOverviewController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.overview', [
            'overview' => $this->overview(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json($this->overview());
    }

    public function auditLog()
    {
        $actions = QueueAction::with(['department', 'actor'])
            ->latest()
            ->paginate(25);

        return view('admin.audit-log', compact('actions'));
    }

    private function overview(): array
    {
        $departments = Department::withCount([
            'staff',
            'staff as active_staff_count' => fn ($query) => $query->where('is_active', true),
        ])->orderBy('name')->get();

        $entries = QueueEntry::whereDate('queue_date', today())
            ->orderBy('id')
            ->get([
                'id',
                'department_id',
                'ticket_number',
                'status',
                'served_at',
                'completed_at',
            ]);

        $entriesByDepartment = $entries->groupBy('department_id');
        $todayStats = $this->statsFor($entries);
        $departmentCards = $departments->map(function (Department $department) use ($entriesByDepartment) {
            $departmentEntries = $entriesByDepartment->get($department->id, collect());
            $stats = $this->statsFor($departmentEntries);
            $serving = $departmentEntries->firstWhere('status', 'serving');

            return [
                'id' => $department->id,
                'name' => $department->name,
                'is_active' => (bool) $department->is_active,
                'queue_paused' => (bool) $department->queue_paused,
                'pause_source' => $department->lunch_break_paused ? 'lunch' : 'manual',
                'staff_count' => (int) $department->staff_count,
                'active_staff_count' => (int) $department->active_staff_count,
                'current_ticket' => $serving?->ticket_number,
                'waiting_count' => $stats['waiting'],
                'completed_count' => $stats['completed'],
                'no_response_count' => $stats['no_response'],
                'average_service_minutes' => $stats['average_service_minutes'],
                'estimated_wait_minutes' => round($stats['waiting'] * $stats['average_service_minutes'], 1),
            ];
        })->values();

        $alerts = $departmentCards->flatMap(function (array $department) {
            $alerts = [];

            if (! $department['is_active']) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "{$department['name']} is inactive.",
                ];
            } elseif ($department['queue_paused']) {
                $source = $department['pause_source'] === 'lunch' ? 'lunch break' : 'manual action';
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "{$department['name']} is paused by {$source}.",
                ];
            }

            if ($department['is_active'] && $department['active_staff_count'] === 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "{$department['name']} has no active staff assigned.",
                ];
            }

            return $alerts;
        })->values();

        $failedSmsCount = SmsNotification::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($failedSmsCount > 0) {
            $alerts->prepend([
                'type' => 'danger',
                'message' => "{$failedSmsCount} SMS notification(s) failed in the last 24 hours.",
            ]);
        }

        return [
            'today' => $todayStats,
            'departments' => $departmentCards,
            'alerts' => $alerts,
            'integrations' => [
                'sms' => [
                    'ready' => (bool) config('services.textbee.key') && (bool) config('services.textbee.device_id'),
                    'label' => config('services.textbee.key') && config('services.textbee.device_id')
                        ? 'Configured'
                        : 'Fallback logging',
                    'failed_count' => $failedSmsCount,
                ],
                'realtime' => [
                    'ready' => config('broadcasting.default') === 'pusher' && (bool) config('broadcasting.connections.pusher.key'),
                    'label' => config('broadcasting.default') === 'pusher' && config('broadcasting.connections.pusher.key')
                        ? 'Pusher enabled'
                        : 'Polling fallback',
                ],
                'queue' => [
                    'ready' => config('queue.default') !== 'sync',
                    'label' => config('queue.default') === 'sync'
                        ? 'Inline processing'
                        : ucfirst((string) config('queue.default')).' worker',
                ],
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function statsFor($entries): array
    {
        $completed = $entries->where('status', 'completed');
        $durations = $completed
            ->filter(fn (QueueEntry $entry) => $entry->served_at && $entry->completed_at)
            ->map(fn (QueueEntry $entry) => $entry->completed_at->diffInSeconds($entry->served_at) / 60);

        $averageServiceMinutes = $durations->count() >= 2
            ? round(max(1, min(30, $durations->avg())), 1)
            : 5.0;

        return [
            'waiting' => $entries->where('status', 'waiting')->count(),
            'serving' => $entries->where('status', 'serving')->count(),
            'completed' => $completed->count(),
            'no_response' => $entries->where('status', 'no_response')->count(),
            'average_service_minutes' => $averageServiceMinutes,
        ];
    }
}
