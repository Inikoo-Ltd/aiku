<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInWooPortfolio
{
    use AsAction;

    public string $commandSignature = 'woo:update-inventory';


    public function handle(): void
    {
        $platform              = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();
        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {


            if ($customerSalesChannel->ban_stock_update_util && $customerSalesChannel->ban_stock_update_util->gt(now())) {
                continue;
            }

            if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
                continue;
            }

            /** @var \App\Models\Dropshipping\WooCommerceUser $wooCommerceUser */
            $wooCommerceUser = $customerSalesChannel->user;

            if (!$wooCommerceUser) {

                continue;
            }

            $wooCommerceUser->setTimeout(60);
            $result = $wooCommerceUser->checkConnection();
            if ($result && Arr::has($result, 'environment')) {

                $customerSalesChannel->update([
                    'ban_stock_update_util' => null
                ]);

                $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                    ->whereNotNull('platform_product_id')
                    ->where('item_type', 'Product')
                    ->where('platform_status', true)
                    ->get();


                $first=true;
                /** @var Portfolio $portfolio */
                foreach ($portfolios as $portfolio) {
                    if ($this->checkIfApplicable($portfolio)) {
                        if($first){
                            UpdateWooPortfolio::run($portfolio->id);
                            $first=false;
                        }else{
                            UpdateWooPortfolio::dispatch($portfolio->id);
                        }
                    }

                }
            }else{
                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addHours(3),
                ]);
            }

        }
    }

    public function checkIfApplicable(Portfolio $portfolio): bool
    {
        $applicable = false;



        if (!$portfolio->stock_last_updated_at) {
            $applicable = true;
        } else {
            /** @var Product $product */
            $product = $portfolio->item;

            if (!$product->available_quantity_updated_at || $product->available_quantity_updated_at->gt($portfolio->stock_last_updated_at)) {
                $applicable = true;
            }
        }

        return $applicable;
    }


    public function asCommand(): void
    {
        $this->handle();
    }
}
