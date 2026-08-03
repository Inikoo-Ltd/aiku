<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 18:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RetinaOrderSubmittedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public int $customerId, public int $orderId)
    {
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-submitted';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('retina.'.$this->customerId.'.customer'),
        ];
    }
}
