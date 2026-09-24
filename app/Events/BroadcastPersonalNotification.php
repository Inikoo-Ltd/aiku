<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastPersonalNotification implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array{id: string|null, title: string, body: string, route: string} $notification
     */
    public function __construct(
        public int $userId,
        public array $notification
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
        return 'personal-notification';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
        ];
    }
}
