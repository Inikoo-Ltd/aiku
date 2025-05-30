<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadProductToWooCommerceProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public WooCommerceUser $wooCommerceUser;
    public Portfolio $portfolio;

    public function __construct(WooCommerceUser $wooCommerceUser, Portfolio $portfolio)
    {
        $this->wooCommerceUser = $wooCommerceUser;
        $this->portfolio       = $portfolio;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("woo.{$this->wooCommerceUser->id}.upload-product.{$this->portfolio->id}")

        ];
    }

    public function broadcastWith(): array
    {
        Log::info('Broadcasting WooCommerce upload progress', [
            'wooCommerceUserId' => $this->wooCommerceUser->id,
            'portfolioId'       => $this->portfolio->id,
        ]);

        return [
            'portfolio' => $this->portfolio,
        ];
    }

    public function broadcastAs(): string
    {
        return 'woo-upload-progress';
    }
}
