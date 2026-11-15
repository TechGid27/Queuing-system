<?php

namespace App\Services;

use App\Models\Counter;
use App\Models\Department;
use App\Models\QueueAction;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QueueTransitionService
{
    public function callNext(Department $department, ?User $actor = null, ?int $counterId = null): QueueEntry
    {
        return DB::transaction(function () use ($department, $actor, $counterId) {
            $lockedDepartment = $this->lockDepartment($department);
            $this->ensureOperational($lockedDepartment);

            // One staff = one serving at a time within the department.
            if ($actor && $actor->role === 'staff') {
                $alreadyServing = $this->todayQueue($lockedDepartment)
                    ->where('status', 'serving')
                    ->where('served_by', $actor->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyServing) {
                    throw new \LogicException('Complete or skip your current ticket before calling the next student.');
                }
            }

            if ($counterId) {
                $counter = $this->resolveFreeCounter($lockedDepartment, $counterId);
                $next = $this->pickNextFor($lockedDepartment, $counter);

                if (! $next) {
                    throw new \LogicException(
                        $this->todayQueue($lockedDepartment)->where('status', 'waiting')->exists()
                            ? "No students waiting for {$counter->name}'s services."
                            : 'No students waiting in this department.'
                    );
                }
            } else {
                // Legacy path (no explicit counter): walk free counters in
                // order and take the first one with an eligible ticket.
                $free = $this->freeCounters($lockedDepartment);

                if ($free->isEmpty() && ! $this->hasAnyCounter($lockedDepartment)) {
                    $free = collect([Counter::create([
                        'department_id' => $lockedDepartment->id,
                        'name' => 'Window 1',
                        'is_active' => true,
                    ])]);
                }

                if ($free->isEmpty()) {
                    throw new \LogicException('All counters are currently serving. Complete or skip a ticket before calling the next student.');
                }

                // Legacy rows without counter_id occupy the default counter.
                $legacyServing = $this->todayQueue($lockedDepartment)
                    ->where('status', 'serving')
                    ->whereNull('counter_id')
                    ->lockForUpdate()
                    ->exists();

                if ($legacyServing) {
                    throw new \LogicException('Complete or skip the current ticket before calling the next student.');
                }

                $counter = null;
                $next = null;
                foreach ($free as $candidate) {
                    $next = $this->pickNextFor($lockedDepartment, $candidate);

                    if ($next) {
                        $counter = $candidate;
                        break;
                    }
                }

                if (! $next) {
                    throw new \LogicException(
                        $this->todayQueue($lockedDepartment)->where('status', 'waiting')->exists()
                            ? 'No students waiting match any free window.'
                            : 'No students waiting in this department.'
                    );
                }
            }

            $next->update([
                'status' => 'serving',
                'served_at' => now(),
                'counter_id' => $counter->id,
                'served_by' => $actor?->id,
            ]);

            $this->record(
                action: 'called',
                department: $lockedDepartment,
                entry: $next,
                actor: $actor,
                fromStatus: 'waiting',
                toStatus: 'serving'
            );

            return $next->fresh(['counter', 'servedBy']);
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

            // Tickets being served at a paused counter are not auto-skipped.
            $pausedCounterIds = Counter::where('department_id', $lockedDepartment->id)
                ->where('queue_paused', true)
                ->pluck('id')
                ->all();

            $expired = $this->todayQueue($lockedDepartment)
                ->where('status', 'serving')
                ->when($pausedCounterIds, function ($query) use ($pausedCounterIds) {
                    $query->where(function ($inner) use ($pausedCounterIds) {
                        $inner->whereNull('counter_id')
                            ->orWhereNotIn('counter_id', $pausedCounterIds);
                    });
                })
                ->where(function ($query) {
                    $query->where('served_at', '<', now()->subMinutes(3))
                        ->orWhere(function ($fallback) {
                            $fallback->whereNull('served_at')
                                ->where('updated_at', '<', now()->subMinutes(3));
                        });
                })
                ->lockForUpdate()
                ->get();

            if ($expired->isEmpty()) {
                return null;
            }

            $skipped = [];
            foreach ($expired as $serving) {
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
                $skipped[] = $serving->fresh();
            }

            // Refill every free counter in order (purpose-aware).
            $called = [];
            foreach ($this->freeCounters($lockedDepartment) as $counter) {
                $next = $this->pickNextFor($lockedDepartment, $counter);

                if (! $next) {
                    continue;
                }

                $next->update([
                    'status' => 'serving',
                    'served_at' => now(),
                    'counter_id' => $counter->id,
                    'served_by' => null,
                ]);
                $this->record(
                    action: 'auto_called',
                    department: $lockedDepartment,
                    entry: $next,
                    fromStatus: 'waiting',
                    toStatus: 'serving'
                );
                $called[] = $next->fresh(['counter']);
            }

            return ['skipped' => $skipped, 'called' => $called];
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

    public function setCounterPaused(Counter $counter, bool $paused, ?User $actor = null, string $source = 'manual'): Counter
    {
        return DB::transaction(function () use ($counter, $paused, $actor, $source) {
            $lockedCounter = Counter::whereKey($counter->id)->lockForUpdate()->firstOrFail();

            if (! $lockedCounter->is_active) {
                throw new \LogicException('Activate this counter before changing its pause status.');
            }

            $department = $this->lockDepartmentById((int) $lockedCounter->department_id);

            if (! $department->is_active) {
                throw new \LogicException('Activate this department before changing its queue status.');
            }

            $wasPaused = (bool) $lockedCounter->queue_paused;
            $lockedCounter->update([
                'queue_paused' => $paused,
                'lunch_break_paused' => $source === 'lunch' ? $paused : false,
            ]);

            if ($wasPaused !== $paused) {
                $this->record(
                    action: $paused ? 'counter_paused' : 'counter_resumed',
                    department: $department,
                    actor: $actor,
                    metadata: ['counter_id' => $lockedCounter->id, 'counter_name' => $lockedCounter->name, 'source' => $source]
                );
            }

            return $lockedCounter->fresh();
        });
    }

    public function setCounterPauseMode(Counter $counter, bool $auto, ?User $actor = null): Counter
    {
        return DB::transaction(function () use ($counter, $auto, $actor) {
            $lockedCounter = Counter::whereKey($counter->id)->lockForUpdate()->firstOrFail();
            $department = $this->lockDepartmentById((int) $lockedCounter->department_id);

            if (! $department->is_active) {
                throw new \LogicException('Activate this department before changing its queue status.');
            }

            $wasAuto = (bool) $lockedCounter->auto_pause_enabled;
            $lockedCounter->update(['auto_pause_enabled' => $auto]);

            if (! $auto && $lockedCounter->lunch_break_paused) {
                $lockedCounter->update(['lunch_break_paused' => false]);
                $lockedCounter->refresh();
            }

            if ($wasAuto !== $auto) {
                $this->record(
                    action: 'counter_pause_mode_changed',
                    department: $department,
                    actor: $actor,
                    metadata: [
                        'counter_id' => $lockedCounter->id,
                        'counter_name' => $lockedCounter->name,
                        'from' => $wasAuto ? 'auto' : 'manual',
                        'to' => $auto ? 'auto' : 'manual',
                    ]
                );
            }

            return $lockedCounter->fresh();
        });
    }

    private function resolveFreeCounter(Department $lockedDepartment, ?int $counterId = null): Counter
    {
        $busyCounterIds = $this->todayQueue($lockedDepartment)
                ->where('status', 'serving')
                ->whereNotNull('counter_id')
                ->pluck('counter_id')
                ->all();

        if ($counterId) {
            $counter = \App\Models\Counter::whereKey($counterId)
                ->where('department_id', $lockedDepartment->id)
                ->lockForUpdate()
                ->first();

            abort_unless($counter && $counter->is_active, 404, 'Counter not available.');

            if ($counter->queue_paused) {
                throw new \LogicException("{$counter->name} is paused. Resume it before calling the next student.");
            }

            if (in_array($counter->id, $busyCounterIds, true)) {
                throw new \LogicException("{$counter->name} is already serving. Choose another counter.");
            }

            return $counter;
        }

        $counter = \App\Models\Counter::where('department_id', $lockedDepartment->id)
            ->where('is_active', true)
            ->where('queue_paused', false)
            ->whereNotIn('id', $busyCounterIds ?: [0])
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            // Only create a default counter for legacy departments that have
            // none. If counters exist but are all busy, do not attempt a
            // duplicate "Window 1" insert (unique department/name constraint).
            $hasCounters = \App\Models\Counter::where('department_id', $lockedDepartment->id)->exists();

            if ($hasCounters) {
                throw new \LogicException('All counters are currently serving. Complete or skip a ticket before calling the next student.');
            }

            $counter = \App\Models\Counter::create([
                'department_id' => $lockedDepartment->id,
                'name' => 'Window 1',
                'is_active' => true,
            ]);
        }

        // Legacy rows without counter_id occupy the default counter.
        $legacyServing = $this->todayQueue($lockedDepartment)
            ->where('status', 'serving')
            ->whereNull('counter_id')
            ->lockForUpdate()
            ->exists();

        if ($legacyServing) {
            throw new \LogicException('Complete or skip the current ticket before calling the next student.');
        }

        return $counter;
    }

    private function freeCounters(Department $lockedDepartment): \Illuminate\Support\Collection
    {
        $busyCounterIds = $this->todayQueue($lockedDepartment)
            ->where('status', 'serving')
            ->whereNotNull('counter_id')
            ->pluck('counter_id')
            ->all();

        return \App\Models\Counter::where('department_id', $lockedDepartment->id)
            ->where('is_active', true)
            ->where('queue_paused', false)
            ->whereNotIn('id', $busyCounterIds ?: [0])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function hasAnyCounter(Department $department): bool
    {
        return \App\Models\Counter::where('department_id', $department->id)->exists();
    }

    /**
     * Waiting tickets this window may call:
     *  - window has no assigned purposes (general window) → everything
     *  - ticket purpose is one of the window's assigned purposes
     *
     * Strict routing: a purpose-scoped window never takes tickets whose
     * purpose (or free-text "Other") is not assigned to it — including
     * purposes assigned to no window at all. Only a general window can
     * take those.
     */
    private function eligibleWaiting(Department $department, Counter $counter)
    {
        $query = $this->todayQueue($department)->where('status', 'waiting');

        $windowPurposeIds = $counter->purposeIds();

        if ($windowPurposeIds === []) {
            return $query;
        }

        // "Other" / unassigned purposes go to the overflow window(s).
        if (in_array($counter->id, Counter::overflowIds($department->id), true)) {
            $assigned = Counter::assignedPurposeIds($department->id);

            return $query->where(function ($q) use ($windowPurposeIds, $assigned) {
                $q->whereIn('purpose_id', $windowPurposeIds)
                    ->orWhereNull('purpose_id')
                    ->orWhereNotIn('purpose_id', $assigned ?: [0]);
            });
        }

        return $query->whereIn('purpose_id', $windowPurposeIds);
    }

    /**
     * A window with fewer assigned purposes than the busiest window in the
     * department has spare capacity, so it may also take tickets of other
     * windows' purposes once its own purposes have nothing waiting.
     */
    private function canHelpOthers(Counter $counter, ?int $maxPurposeCount = null): bool
    {
        $mine = count($counter->purposeIds());
        if ($mine === 0) {
            return false; // general window already takes everything
        }

        $max = $maxPurposeCount ?? DB::table('counter_purpose')
            ->join('counters', 'counters.id', '=', 'counter_purpose.counter_id')
            ->where('counters.department_id', $counter->department_id)
            ->where('counters.is_active', true)
            ->where('counters.queue_paused', false)
            ->groupBy('counter_purpose.counter_id')
            ->selectRaw('COUNT(*) as total')
            ->pluck('total')
            ->max();

        return $mine < (int) $max;
    }

    /**
     * Next ticket for a window: its own purposes first (oldest first), then,
     * for a lighter window, any other waiting ticket.
     */
    private function pickNextFor(Department $department, Counter $counter): ?QueueEntry
    {
        $own = $this->eligibleWaiting($department, $counter)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($own || ! $this->canHelpOthers($counter)) {
            return $own;
        }

        return $this->todayQueue($department)
            ->where('status', 'waiting')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * How many waiting tickets each counter may call right now,
     * keyed by counter id. Drives the per-window "Call Next" buttons.
     */
    public function eligibleWaitingCounts(Department $department, $counters): array
    {
        $waitingByPurpose = $this->todayQueue($department)
            ->where('status', 'waiting')
            ->groupBy('purpose_id')
            ->selectRaw('purpose_id, COUNT(*) as total')
            ->pluck('total', 'purpose_id');

        $byPurpose = $waitingByPurpose
            ->except('')
            ->map(fn ($total) => (int) $total);
        $totalWaiting = (int) $waitingByPurpose->sum();

        $maxPurposeCount = collect($counters)->where('is_active', true)->where('queue_paused', false)
            ->map(fn ($c) => count($c->purposeIds()))->max() ?? 0;

        $assigned = Counter::assignedPurposeIds($department->id);
        $unassignedCount = $totalWaiting - (int) $byPurpose->only($assigned)->sum();
        $overflowIds = Counter::overflowIds($department->id);

        $counts = [];
        foreach ($counters as $counter) {
            $purposeIds = $counter->purposeIds();
            $counts[$counter->id] = ($purposeIds === [] || $this->canHelpOthers($counter, $maxPurposeCount))
                ? $totalWaiting
                : (int) $byPurpose->only($purposeIds)->sum()
                    + (in_array($counter->id, $overflowIds, true) ? $unassignedCount : 0);
        }

        return $counts;
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
            ->where('queue_date', today()->toDateString());
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
