<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastTicketBadgeUpdate implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $ticketBadges
     * @param array{title: string, body: string, route: string}|null $notification
     */
    public function __construct(
        public int $userId,
        public array $ticketBadges,
        public ?array $notification = null
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
        return 'ticket-badges-update';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_badges' => $this->ticketBadges,
            'notification'  => $this->notification,
        ];
    }
}
