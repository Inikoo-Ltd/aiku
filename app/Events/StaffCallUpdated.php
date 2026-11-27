<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\StaffCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffCallUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public StaffCall $call)
    {
    }

    /**
     * Each person still in the conversation, everyone who has been in the call and whoever made its meeting link, on
     * their own private channel: the ring and the call state reach no one else, and never a guest.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $userId) => new PrivateChannel('grp.personal.'.$userId), $this->call->watcherIds());
    }

    public function broadcastAs(): string
    {
        return 'staff-call';
    }

    public function broadcastWith(): array
    {
        return $this->call->fresh()->toBroadcast();
    }
}
