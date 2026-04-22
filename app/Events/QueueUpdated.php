<?php

namespace App\Events;

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

    public function __construct(
        string $currentNumber,
        string $nextNumber,
        int $waitingCount,
        ?string $completedTicket = null,
        ?string $skippedTicket = null
    ) {
        $this->currentNumber   = $currentNumber;
        $this->nextNumber      = $nextNumber;
        $this->waitingCount    = $waitingCount;
        $this->completedTicket = $completedTicket;
        $this->skippedTicket   = $skippedTicket;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('queue');
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    public function broadcastWith(): array
    {
        $waitingStudents = \App\Models\QueueEntry::where('status', 'waiting')
            ->orderBy('id', 'asc')
            ->get(['id', 'ticket_number', 'name', 'purpose'])
            ->map(fn($s, $i) => array_merge($s->toArray(), ['position' => $i + 1]))
            ->values();

        $currentServing = \App\Models\QueueEntry::where('status', 'serving')
            ->first(['id', 'ticket_number', 'name', 'purpose', 'phone_number']);

        return [
            'current'          => $this->currentNumber,
            'next'             => $this->nextNumber,
            'waiting_count'    => $this->waitingCount,
            'waiting_list'     => $waitingStudents,
            'current_serving'  => $currentServing,
            'completed_ticket' => $this->completedTicket,
            'skipped_ticket'   => $this->skippedTicket,
        ];
    }
}
