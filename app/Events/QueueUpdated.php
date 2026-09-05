<?php

namespace App\Events;

use App\Http\Controllers\StaffController;
use App\Models\Department;
use App\Models\QueueEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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

    public function broadcastOn(): Channel
    {
        return new Channel("queue.{$this->departmentId}");
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    public function broadcastWith(): array
    {
        $waitingStudents = QueueEntry::where('department_id', $this->departmentId)
            ->whereDate('queue_date', today())
            ->where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->get(['ticket_number'])
            ->map(fn ($entry, $index) => [
                'ticket_number' => $entry->ticket_number,
                'position' => $index + 1,
            ])
            ->values();

        $currentServing = QueueEntry::where('department_id', $this->departmentId)
            ->whereDate('queue_date', today())
            ->where('status', 'serving')
            ->first(['ticket_number']);

        $department = Department::find($this->departmentId);
        $avgServeMins = StaffController::getAvgServeMinutes($this->departmentId);

        return [
            'department_id'    => $this->departmentId,
            'department_name'  => $department?->name,
            'current'          => $this->currentNumber,
            'next'             => $this->nextNumber,
            'waiting_count'    => $this->waitingCount,
            'waiting_list'     => $waitingStudents,
            'current_serving'  => $currentServing,
            'completed_ticket' => $this->completedTicket,
            'skipped_ticket'   => $this->skippedTicket,
            'queue_paused'     => (bool) $department?->queue_paused,
            'pause_source'     => $department?->lunch_break_paused ? 'lunch' : 'manual',
            'avg_serve_mins'   => $avgServeMins,
        ];
    }
}
