<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class DeleteAmazonProduct extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Portfolio $portfolio)
    {
        try {
            /** @var AmazonUser $amazonUser */
            $amazonUser = $portfolio->customerSalesChannel->user;
            $amazonUser->deleteProduct($portfolio->platform_product_id);
        } catch (\Exception $e) {
            Log::info("Failed to delete product due to: " . $e->getMessage());
            Sentry::captureMessage("Failed to delete product due to: " . $e->getMessage());
        }
    }



}
