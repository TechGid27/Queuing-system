<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurposesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct()
    {
        //
    }

    public function broadcastOn(): Channel
    {
        return new Channel('purposes');
    }

    public function broadcastAs(): string
    {
        return 'purposes.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'timestamp' => now()->timestamp,
        ];
    }
}
