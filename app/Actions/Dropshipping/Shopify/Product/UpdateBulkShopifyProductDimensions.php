<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBulkShopifyProductDimensions
{
    use AsAction;
    use WithShopifyApi;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->where('status', true)
            ->where('platform_status', true)
            ->whereNotNull('platform_product_id')
            ->whereNotNull('platform_product_variant_id')
            ->get()
            ->chunk(50);

        foreach ($portfolios as $portfoliosChunk) {
            foreach ($portfoliosChunk as $portfolio) {
                UpdateShopifyProductDimensions::run($customerSalesChannel, $portfolio);
            }
        }
    }
}
