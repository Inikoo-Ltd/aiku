<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadProductToMagentoProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public MagentoUser $magentoUser;
    public Portfolio $portfolio;

    public function __construct(MagentoUser $magentoUser, Portfolio $portfolio)
    {
        $this->magentoUser = $magentoUser;
        $this->portfolio       = $portfolio;
    }

    public function broadcastOn(): array
    {
        Log::info('Broadcasting Magento upload progress', $this->portfolio->toArray());
        return [
            new PrivateChannel("magento.{$this->magentoUser->id}.upload-product.{$this->portfolio->id}")

        ];
    }

    public function broadcastWith(): array
    {
        Log::info('Broadcasting WooCommerce upload progress', [
            'magentoUserId' => $this->magentoUser->id,
            'portfolioId'       => $this->portfolio->id,
        ]);

        return [
            'portfolio' => $this->portfolio,
        ];
    }

    public function broadcastAs(): string
    {
        return 'magento-upload-progress';
    }
}
