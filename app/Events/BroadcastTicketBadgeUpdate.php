<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Models\SysAdmin\User;
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
     * @param array{title: string, body: string, route: string}|null $notification
     */
    public function __construct(public User $user, public ?array $notification = null)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.personal.'.$this->user->id)];
    }

    public function broadcastAs(): string
    {
        return 'ticket-badges-update';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_badges' => GetTicketBadgeData::run($this->user),
            'notification'  => $this->notification,
        ];
    }
}
