<?php

namespace App\Services;

use App\Models\QueueEntry;
use Illuminate\Support\Facades\Cache;

class QueueMetricsService
{
    /**
     * Average service time (served_at → completed_at) for today's completed
     * tickets in a department. Clamped to a sensible 1–30 minute range and
     * cached briefly because it is read on every poll/dashboard request.
     */
    public function averageServeMinutes(?int $departmentId = null): float
    {
        if (! $departmentId) {
            return 5.0;
        }

        return Cache::remember(
            "avg_serve_mins_{$departmentId}_".today()->toDateString(),
            60,
            function () use ($departmentId) {
                $completed = QueueEntry::where('department_id', $departmentId)
                    ->where('queue_date', today()->toDateString())
                    ->where('status', 'completed')
                    ->whereNotNull('served_at')
                    ->whereNotNull('completed_at')
                    ->get(['served_at', 'completed_at']);

                if ($completed->count() < 2) {
                    return 5.0;
                }

                $totalSeconds = $completed->sum(
                    fn ($entry) => $entry->completed_at->diffInSeconds($entry->served_at)
                );

                return round(max(1, min(30, ($totalSeconds / $completed->count()) / 60)), 1);
            }
        );
    }
}
