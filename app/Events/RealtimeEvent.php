<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $module,
        public string $action,
        public array $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('realtime')
        ];
    }

    public function broadcastAs(): string
    {
        return 'realtime.event';
    }

    public function broadcastWith(): array
    {
        return [
            'module' => $this->module,
            'action' => $this->action,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}