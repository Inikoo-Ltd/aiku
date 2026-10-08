<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBulkShopifyProductDimensions
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $failures = [];

        Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('status', true)
            ->where('platform_status', true)
            ->whereNotNull('platform_product_id')
            ->whereNotNull('platform_product_variant_id')
            ->chunkById(50, function ($portfolios) use ($customerSalesChannel, &$failures) {
                foreach ($portfolios as $portfolio) {
                    [$updated, $error] = UpdateShopifyProductDimensions::run($customerSalesChannel, $portfolio);
                    if (!$updated) {
                        $failures[$portfolio->id] = $error;
                    }
                }
            });

        if ($failures) {
            Log::warning('Shopify specifications not updated', ['customer_sales_channel_id' => $customerSalesChannel->id, 'failures' => $failures]);
        }
    }
}
