<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchProductFromShopifyProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public ShopifyUser $shopifyUser;
    public array $modelData;

    public function __construct(ShopifyUser $shopifyUser, array $modelData)
    {
        $this->shopifyUser     = $shopifyUser;
        $this->modelData     = $modelData;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shopify.{$this->shopifyUser->id}.fetch-product")
        ];
    }

    public function broadcastWith(): array
    {
        Log::info('info:' . json_encode($this->modelData));
        return $this->modelData;
    }

    public function broadcastAs(): string
    {
        return 'shopify-fetch-progress';
    }
}
