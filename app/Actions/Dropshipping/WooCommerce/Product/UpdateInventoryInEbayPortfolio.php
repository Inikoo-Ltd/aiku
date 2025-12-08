<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInEbayPortfolio
{
    use AsAction;

    public string $commandSignature = 'ebay:update-inventory';


    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        $platform              = Platform::where('type', PlatformTypeEnum::EBAY)->first();

        if ($customerSalesChannel === null) {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('id', $customerSalesChannel->id)
                ->get();
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {


            if ($customerSalesChannel->ban_stock_update_util && $customerSalesChannel->ban_stock_update_util->gt(now())) {
                continue;
            }

            if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
                continue;
            }

            /** @var \App\Models\Dropshipping\EbayUser $ebayUser */
            $ebayUser = $customerSalesChannel->user;

            if (!$ebayUser) {

                continue;
            }

            $ebayUser->setTimeout(20);
            $result = $ebayUser->getUser();

            if ($result && Arr::has($result, 'username')) {

                $customerSalesChannel->update([
                    'ban_stock_update_util' => null
                ]);

                $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                    ->whereNotNull('platform_product_id')
                    ->where('item_type', 'Product')
                    ->where('platform_status', true)
                    ->get();


                $first = true;
                /** @var Portfolio $portfolio */
                foreach ($portfolios as $portfolio) {
                    if ($this->checkIfApplicable($portfolio)) {
                        if ($first) {
                            $ebayUser->setTimeout(45);
                            UpdateEbayPortfolio::run($portfolio->id);
                            $first = false;
                        } else {
                            // Add jitter to spread API calls and avoid bursts
                            $delaySeconds = random_int(1, 120);
                            UpdateEbayPortfolio::dispatch($portfolio->id)->delay(now()->addSeconds($delaySeconds));
                        }
                    }

                }
            } else {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
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
