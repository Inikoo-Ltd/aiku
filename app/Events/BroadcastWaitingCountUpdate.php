<?php

/*
 * Author: Vika Aqordi
 * Created on 23-04-2026-15h-31m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastWaitingCountUpdate implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public int $dispatchingWaitingCount,
        public int $crmWaitingCount
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.personal.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'waiting-items-count-update';
    }

    public function broadcastWith(): array
    {
        return [
            'dispatching_waiting_count' => $this->dispatchingWaitingCount,
            'crm_waiting_count'         => $this->crmWaitingCount,
        ];
    }
}
