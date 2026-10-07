<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastNewOrderAlert implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public bool $afterCommit = true;

    /**
     * @param  array{order_id: int, types: array<int, string>, reference: string, customer: string, amount: float, currency: string, is_unpaid: bool, url: string}  $alert
     */
    public function __construct(public int $shopId, public array $alert)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.shop.'.$this->shopId.'.new-orders')];
    }

    public function broadcastAs(): string
    {
        return 'new-order';
    }

    public function broadcastWith(): array
    {
        return $this->alert + ['shop_id' => $this->shopId];
    }
}
