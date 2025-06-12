<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Events;

use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadProductToAmazonProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public AmazonUser $amazonUser;
    public Portfolio $portfolio;

    public function __construct(AmazonUser $amazonUser, Portfolio $portfolio)
    {
        $this->amazonUser = $amazonUser;
        $this->portfolio = $portfolio;
    }

    public function broadcastOn(): array
    {
        Log::info('Broadcasting Amazon upload progress', $this->portfolio->toArray());
        return [
            new PrivateChannel("amazon.{$this->amazonUser->id}.upload-product.{$this->portfolio->id}")

        ];
    }

    public function broadcastWith(): array
    {
        Log::info('Broadcasting Amazon upload progress', [
            'amazonUserId' => $this->amazonUser->id,
            'portfolioId' => $this->portfolio->id,
        ]);

        return [
            'portfolio' => $this->portfolio,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ebay-upload-progress';
    }
}
