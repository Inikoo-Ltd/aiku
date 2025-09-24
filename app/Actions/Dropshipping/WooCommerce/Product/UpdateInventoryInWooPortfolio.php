<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class UpdateInventoryInWooPortfolio
{
    use AsAction;

    public string $commandSignature  = 'woo:update-inventory';

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();
        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)->get();

        $productData = [];
        foreach ($customerSalesChannels as $customerSalesChannel) {
            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereNotNull('platform_product_id')
                ->get();

            foreach ($portfolios as $portfolio) {
                $product = $portfolio->item;

                $productData['update'][] =
                        [
                            "id" => $portfolio->platform_product_id,
                            "stock_quantity" => $product->available_quantity,
                        ];
            }


            /** @var WooCommerceUser $wooCommerceUser */
            $wooCommerceUser = $customerSalesChannel->user;

            if (! blank($productData) && $wooCommerceUser) {
                try {
                    $wooCommerceUser->batchUpdateWooCommerceProducts($productData);
                } catch (\Exception $e) {
                    Sentry::captureException($e);
                }
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
