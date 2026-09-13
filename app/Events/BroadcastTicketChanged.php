<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Helpers\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastTicketChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public int $groupId;
    public int $id;
    public string $reference;

    public function __construct(Ticket $ticket)
    {
        $this->groupId   = $ticket->group_id;
        $this->id        = $ticket->id;
        $this->reference = $ticket->reference;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.'.$this->groupId.'.general')];
    }

    public function broadcastAs(): string
    {
        return 'ticket-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->id,
            'reference' => $this->reference,
        ];
    }
}
