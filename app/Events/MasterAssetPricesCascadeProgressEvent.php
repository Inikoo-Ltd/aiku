<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Masters\MasterAsset;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterAssetPricesCascadeProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array{state: string, done: int, total: int} $progress */
    public function __construct(
        public MasterAsset $masterAsset,
        public array $progress
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.master-asset.'.$this->masterAsset->id)
        ];
    }

    public function broadcastWith(): array
    {
        return $this->progress;
    }

    public function broadcastAs(): string
    {
        return 'prices-cascade-progress';
    }
}
