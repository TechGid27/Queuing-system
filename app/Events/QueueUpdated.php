<?php

namespace App\Events;

use App\Models\Department;
use App\Models\QueueEntry;
use App\Services\QueueMetricsService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $currentNumber;
    public string $nextNumber;
    public int $waitingCount;
    public ?string $completedTicket;
    public ?string $skippedTicket;

    public int $departmentId;

    public function __construct(
        int $departmentId,
        string $currentNumber,
        string $nextNumber,
        int $waitingCount,
        ?string $completedTicket = null,
        ?string $skippedTicket = null
    ) {
        $this->departmentId = $departmentId;
        $this->currentNumber   = $currentNumber;
        $this->nextNumber      = $nextNumber;
        $this->waitingCount    = $waitingCount;
        $this->completedTicket = $completedTicket;
        $this->skippedTicket   = $skippedTicket;
    }

    /**
     * Public channel for TV/student displays (sanitized payload) and a
     * private channel for authenticated staff of the department.
     *
     * @return array<int, Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("queue.{$this->departmentId}"),
            new PrivateChannel("queue.{$this->departmentId}.staff"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    public function broadcastWith(): array
    {
        $waitingStudents = QueueEntry::where('department_id', $this->departmentId)
            ->where('queue_date', today()->toDateString())
            ->where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->get(['ticket_number'])
            ->map(fn ($entry, $index) => [
                'ticket_number' => $entry->ticket_number,
                'position' => $index + 1,
            ])
            ->values();

        $currentServings = QueueEntry::where('department_id', $this->departmentId)
            ->where('queue_date', today()->toDateString())
            ->where('status', 'serving')
            ->with('counter')
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'ticket_number' => $s->ticket_number,
                'counter_id' => $s->counter_id,
                'counter_name' => $s->counter?->name,
            ])
            ->values();
        $currentServing = QueueEntry::where('department_id', $this->departmentId)
            ->where('queue_date', today()->toDateString())
            ->where('status', 'serving')
            ->first(['ticket_number']);

        $department = Department::find($this->departmentId);
        $avgServeMins = app(QueueMetricsService::class)->averageServeMinutes($this->departmentId);

        // Per-window (counter) pause state so staff consoles can sync the
        // currently selected window instead of only department-wide state.
        $counters = \App\Models\Counter::where('department_id', $this->departmentId)
            ->orderBy('id')
            ->get(['id', 'name', 'is_active', 'queue_paused', 'auto_pause_enabled', 'lunch_break_paused'])
            ->values();

        return [
            'department_id'    => $this->departmentId,
            'department_name'  => $department?->name,
            'current'          => $this->currentNumber,
            'next'             => $this->nextNumber,
            'waiting_count'    => $this->waitingCount,
            'waiting_list'     => $waitingStudents,
            'current_serving'  => $currentServing,
            'current_servings' => $currentServings,
            'serving_count'    => $currentServings->count(),
            'completed_ticket' => $this->completedTicket,
            'skipped_ticket'   => $this->skippedTicket,
            'queue_paused'     => (bool) $department?->queue_paused,
            'pause_source'     => $department?->lunch_break_paused ? 'lunch' : 'manual',
            'counters'         => $counters,
            'avg_serve_mins'   => $avgServeMins,
        ];
    }
}
