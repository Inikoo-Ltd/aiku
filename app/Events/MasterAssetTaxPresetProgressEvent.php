<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Masters\MasterAsset;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterAssetTaxPresetProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array{state: string, baskets_done: int, baskets_total: int, started_at: string} $progress */
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
        return 'tax-preset-progress';
    }
}
