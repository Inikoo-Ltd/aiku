<?php

/*
 * Author Louis Perez
 * Created on 01-10-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastTicketUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public int $ticketId, public int $groupId)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.ticket.'.$this->ticketId),
            new PrivateChannel('grp.'.$this->groupId.'.general'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket-updated';
    }

    /**
     * @return array{id: int}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->ticketId];
    }
}
