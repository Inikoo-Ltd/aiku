<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-15h-43m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastRetinaTicketBadgeUpdate implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $ticketBadges
     */
    public function __construct(
        public int $webUserId,
        public array $ticketBadges
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.personal.'.$this->webUserId),
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
        ];
    }
}
