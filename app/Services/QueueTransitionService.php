<?php

namespace App\Services;

use App\Models\Department;
use App\Models\QueueAction;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QueueTransitionService
{
    public function callNext(Department $department, ?User $actor = null): QueueEntry
    {
        return DB::transaction(function () use ($department, $actor) {
            $lockedDepartment = $this->lockDepartment($department);
            $this->ensureOperational($lockedDepartment);

            $serving = $this->todayQueue($lockedDepartment)
                ->where('status', 'serving')
                ->lockForUpdate()
                ->first();

            if ($serving) {
                throw new \LogicException('Complete or skip the current ticket before calling the next student.');
            }

            $next = $this->todayQueue($lockedDepartment)
                ->where('status', 'waiting')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                throw new \LogicException('No students waiting in this department.');
            }

            $next->update([
                'status' => 'serving',
                'served_at' => now(),
            ]);

            $this->record(
                action: 'called',
                department: $lockedDepartment,
                entry: $next,
                actor: $actor,
                fromStatus: 'waiting',
                toStatus: 'serving'
            );

            return $next->fresh();
        });
    }

    public function complete(QueueEntry $entry, ?User $actor = null): QueueEntry
    {
        return DB::transaction(function () use ($entry, $actor) {
            $department = $this->lockDepartmentById((int) $entry->department_id);
            $this->ensureOperational($department);
            $lockedEntry = $this->lockEntry($entry);

            if ($lockedEntry->status !== 'serving') {
                throw new \LogicException('Only the currently serving ticket can be completed.');
            }

            $lockedEntry->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->record(
                action: 'completed',
                department: $department,
                entry: $lockedEntry,
                actor: $actor,
                fromStatus: 'serving',
                toStatus: 'completed'
            );

            return $lockedEntry->fresh();
        });
    }

    public function skip(QueueEntry $entry, ?User $actor = null, string $action = 'no_response'): QueueEntry
    {
        return DB::transaction(function () use ($entry, $actor, $action) {
            $department = $this->lockDepartmentById((int) $entry->department_id);
            $this->ensureOperational($department);
            $lockedEntry = $this->lockEntry($entry);

            if ($lockedEntry->status !== 'serving') {
                throw new \LogicException('Only the currently serving ticket can be skipped.');
            }

            $lockedEntry->update([
                'status' => 'no_response',
                'completed_at' => now(),
            ]);

            $this->record(
                action: $action,
                department: $department,
                entry: $lockedEntry,
                actor: $actor,
                fromStatus: 'serving',
                toStatus: 'no_response'
            );

            return $lockedEntry->fresh();
        });
    }

    public function autoSkip(Department $department): ?array
    {
        return DB::transaction(function () use ($department) {
            $lockedDepartment = $this->lockDepartment($department);

            if (! $lockedDepartment->is_active || $lockedDepartment->queue_paused) {
                return null;
            }

            $serving = $this->todayQueue($lockedDepartment)
                ->where('status', 'serving')
                ->where(function ($query) {
                    $query->where('served_at', '<', now()->subMinutes(3))
                        ->orWhere(function ($fallback) {
                            $fallback->whereNull('served_at')
                                ->where('updated_at', '<', now()->subMinutes(3));
                        });
                })
                ->lockForUpdate()
                ->first();

            if (! $serving) {
                return null;
            }

            $serving->update([
                'status' => 'no_response',
                'completed_at' => now(),
            ]);
            $this->record(
                action: 'auto_skipped',
                department: $lockedDepartment,
                entry: $serving,
                fromStatus: 'serving',
                toStatus: 'no_response'
            );

            $next = $this->todayQueue($lockedDepartment)
                ->where('status', 'waiting')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($next) {
                $next->update([
                    'status' => 'serving',
                    'served_at' => now(),
                ]);
                $this->record(
                    action: 'auto_called',
                    department: $lockedDepartment,
                    entry: $next,
                    fromStatus: 'waiting',
                    toStatus: 'serving'
                );
            }

            return [$serving->fresh(), $next?->fresh()];
        });
    }

    public function setPaused(Department $department, bool $paused, ?User $actor = null, string $source = 'manual'): Department
    {
        return DB::transaction(function () use ($department, $paused, $actor, $source) {
            $lockedDepartment = $this->lockDepartment($department);

            if (! $lockedDepartment->is_active) {
                throw new \LogicException('Activate this department before changing its queue status.');
            }

            $wasPaused = (bool) $lockedDepartment->queue_paused;
            $lockedDepartment->update([
                'queue_paused' => $paused,
                'lunch_break_paused' => $source === 'lunch' ? $paused : false,
            ]);

            if ($wasPaused !== $paused) {
                $this->record(
                    action: $paused ? 'paused' : 'resumed',
                    department: $lockedDepartment,
                    actor: $actor,
                    metadata: ['source' => $source]
                );
            }

            return $lockedDepartment->fresh();
        });
    }

    private function lockDepartment(Department $department): Department
    {
        return $this->lockDepartmentById((int) $department->id);
    }

    private function lockDepartmentById(int $departmentId): Department
    {
        return Department::whereKey($departmentId)->lockForUpdate()->firstOrFail();
    }

    private function lockEntry(QueueEntry $entry): QueueEntry
    {
        return QueueEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
    }

    private function todayQueue(Department $department)
    {
        return QueueEntry::where('department_id', $department->id)
            ->whereDate('queue_date', today());
    }

    private function ensureOperational(Department $department): void
    {
        if (! $department->is_active) {
            throw new \LogicException('Activate this department before changing its queue.');
        }

        if ($department->queue_paused) {
            throw new \LogicException('Resume this department queue before changing its queue.');
        }
    }

    private function record(
        string $action,
        Department $department,
        ?QueueEntry $entry = null,
        ?User $actor = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $metadata = []
    ): void {
        QueueAction::create([
            'department_id' => $department->id,
            'queue_entry_id' => $entry?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'ticket_number' => $entry?->ticket_number,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata ?: null,
        ]);
    }
}
