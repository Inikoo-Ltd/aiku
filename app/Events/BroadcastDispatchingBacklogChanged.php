<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastDispatchingBacklogChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public int $organisationId)
    {
    }

    public static function channelName(int $organisationId): string
    {
        return 'grp.org.'.$organisationId.'.dispatching-backlog';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->organisationId))];
    }

    public function broadcastAs(): string
    {
        return 'dispatching-backlog-changed';
    }

    public function broadcastWith(): array
    {
        return ['organisation_id' => $this->organisationId];
    }
}
