<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastProductionQueuesChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public int $organisationId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.org.'.$this->organisationId.'.production-queues')];
    }

    public function broadcastAs(): string
    {
        return 'production-queues-changed';
    }

    public function broadcastWith(): array
    {
        return ['organisation_id' => $this->organisationId];
    }
}
