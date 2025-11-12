<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $stock = [];
            foreach (
                DB::table('portfolios')->select('portfolios.id', 'sku', 'available_quantity')
                    ->where('portfolios.customer_sales_channel_id', $channelId)
                    ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
                    ->where('portfolios.item_type', 'Product')->get() as $stockData
            ) {
                if ($stockData->sku === null) {
                    continue;
                }
                $stock[$stockData->sku] = $stockData->available_quantity;
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
