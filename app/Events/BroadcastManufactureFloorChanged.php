<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BroadcastManufactureFloorChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(public int $productionId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.production.'.$this->productionId.'.floor')];
    }

    public function broadcastAs(): string
    {
        return 'floor-changed';
    }

    public function broadcastWith(): array
    {
        return ['production_id' => $this->productionId];
    }
}
