<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Masters\MasterShop;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterShopPriceExchangeProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array{state: string, done: int, total: int, baskets_total?: int, started_at: string, error?: string} $progress */
    public function __construct(
        public MasterShop $masterShop,
        public string $currencyCode,
        public array $progress
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('grp.master-shop.'.$this->masterShop->id)
        ];
    }

    public function broadcastWith(): array
    {
        return array_merge(['currency' => $this->currencyCode], $this->progress);
    }

    public function broadcastAs(): string
    {
        return 'price-exchange-progress';
    }
}
