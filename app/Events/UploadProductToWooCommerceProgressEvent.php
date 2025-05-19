<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadProductToWooCommerceProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public WooCommerceUser $wooCommerceUser;
    public int $totalProduct;
    public int $uploadedProduct;

    public function __construct(WooCommerceUser $wooCommerceUser, int $totalProduct, int $uploadedProduct)
    {
        $this->wooCommerceUser = $wooCommerceUser;
        $this->totalProduct    = $totalProduct;
        $this->uploadedProduct = $uploadedProduct;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('woo-commerce.upload-product.' . $this->wooCommerceUser->id)
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'total_products'    => $this->totalProduct,
            'uploaded_products' => $this->uploadedProduct
        ];
    }

    public function broadcastAs(): string
    {
        return 'action-progress';
    }
}
