<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class DeleteEbayProduct extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Portfolio $portfolio)
    {
        try {
            /** @var EbayUser $ebayUser */
            $ebayUser = $portfolio->customerSalesChannel->user;
            $ebayUser->withdrawOffer($portfolio->platform_product_id);
        } catch (\Exception $e) {
            Log::info("Failed to delete product due to: " . $e->getMessage());
            Sentry::captureMessage("Failed to delete product due to: " . $e->getMessage());
        }
    }
}
