<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooCustomerSalesChannelPortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackFetchStock extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * Cache stock payload for 5 minutes per customer sales channel and dispatch background updater.
     *
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser): array
    {
        $channelId = $shopifyUser->customer_sales_channel_id;
        if (!$channelId) {
            return [];
        }

        $cacheKey = "shopify:fetch_stock:channel:".$channelId;

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($channelId) {
            /** @var CustomerSalesChannel $customerSalesChannel */
            $customerSalesChannel = CustomerSalesChannel::findOrFail($channelId);

            $products = Product::query()
                ->whereIn(
                    'id',
                    Portfolio::where('customer_sales_channel_id', $channelId)
                        ->where('item_type', 'Product')
                        ->pluck('item_id')
                )
                ->get()
                ->keyBy('id');

            $stock = [];
            foreach (
                Portfolio::where('customer_sales_channel_id', $channelId)
                    ->where('item_type', 'Product')
                    ->get(['sku', 'item_id']) as $portfolio
            ) {
                if ($portfolio->sku === null) {
                    continue;
                }

                $product = $products->get($portfolio->item_id);
                if (!$product) {
                    continue;
                }

                $stock[$portfolio->sku] = UpdateWooCustomerSalesChannelPortfolio::quantityToSend($product, $customerSalesChannel);
            }

            return $stock;
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): array
    {
        if (!$shopifyUser->customer_id) {
            abort(422);
        }

        $this->initialisation($shopifyUser->organisation, $request);

        return $this->handle($shopifyUser);
    }
}
