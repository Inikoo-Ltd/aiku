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

class UploadProductToShopifyProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public ShopifyUser $shopifyUser;
    public Portfolio $portfolio;

    public function __construct(ShopifyUser $shopifyUser, Portfolio $portfolio)
    {
        $this->shopifyUser     = $shopifyUser;
        $this->portfolio     = $portfolio;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shopify.{$this->shopifyUser->id}.upload-product.{$this->portfolio->id}")
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'portfolio'    => $this->portfolio
        ];
    }

    public function broadcastAs(): string
    {
        return 'shopify-upload-progress';
    }
}
