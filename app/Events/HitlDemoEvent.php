<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class HitlDemoEvent implements ShouldBroadcastNow
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $type,
        public readonly array $data,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('hitl-demo.'.$this->runId);
    }

    public function broadcastAs(): string
    {
        return 'demo.event';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'data' => $this->data];
    }
}
