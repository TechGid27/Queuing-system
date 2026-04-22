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

    public function __construct(string $currentNumber, string $nextNumber, int $waitingCount)
    {
        $this->currentNumber = $currentNumber;
        $this->nextNumber    = $nextNumber;
        $this->waitingCount  = $waitingCount;
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
        return [
            'current'       => $this->currentNumber,
            'next'          => $this->nextNumber,
            'waiting_count' => $this->waitingCount,
        ];
    }
}
