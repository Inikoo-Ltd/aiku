<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Dec 2023 14:06:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class UploadProductToSalesChannelProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public CustomerSalesChannel $customerSalesChannel;
    public Portfolio $portfolio;
    public array $statistics;

    public function __construct(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, array $statistics)
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->portfolio  = $portfolio;
        $this->statistics  = $statistics;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("channel.{$this->customerSalesChannel->id}.upload-product.{$this->portfolio->id}")
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'portfolio' => $this->portfolio,
            'statistics' => [
                'total' => Arr::get($this->statistics, 'total'),
                'fail' => Arr::get($this->statistics, 'fail'),
                'success' => Arr::get($this->statistics, 'success')
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'channel-upload-progress';
    }
}
