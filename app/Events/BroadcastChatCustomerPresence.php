<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the agent looking at a website chat that the customer is still on the site, or has left it.
 */
class BroadcastChatCustomerPresence implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{seen_at: string, left: bool}  $presence
     */
    public function __construct(public string $ulid, public array $presence)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel("chat-session.$this->ulid")];
    }

    public function broadcastAs(): string
    {
        return 'customer_presence';
    }

    public function broadcastWith(): array
    {
        return $this->presence;
    }
}
