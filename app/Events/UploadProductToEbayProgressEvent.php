<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Events;

use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadProductToEbayProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public EbayUser $ebayUser;
    public Portfolio $portfolio;

    public function __construct(EbayUser $ebayUser, Portfolio $portfolio)
    {
        $this->ebayUser     = $ebayUser;
        $this->portfolio    = $portfolio;
    }

    public function broadcastOn(): array
    {
        Log::info('Broadcasting WooCommerce upload progress', $this->portfolio->toArray());
        return [
            new PrivateChannel("ebay.{$this->ebayUser->id}.upload-product.{$this->portfolio->id}")

        ];
    }

    public function broadcastWith(): array
    {
        Log::info('Broadcasting eBay upload progress', [
            'ebayUserId' => $this->ebayUser->id,
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
