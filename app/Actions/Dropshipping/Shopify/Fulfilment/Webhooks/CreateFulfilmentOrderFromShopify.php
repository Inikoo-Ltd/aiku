<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks;

use App\Actions\Dropshipping\Shopify\Fulfilment\StoreFulfilmentFromShopify;
use App\Actions\Dropshipping\Shopify\Order\StoreOrderFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CreateFulfilmentOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $fulfillmentOrder): void
    {
        DB::transaction(function () use ($shopifyUser, $fulfillmentOrder) {
            $assignedLineItems = [];
            $shopifyUser->debugWebhooks()->create([
                'data' => $fulfillmentOrder
            ]);

            $destination = $fulfillmentOrder['destination'];
            $lineItems = $fulfillmentOrder['lineItems']['edges'];

            data_set($fulfillmentOrder, 'shipping_address', $destination);
            data_set($fulfillmentOrder, 'customer', $fulfillmentOrder['order']['customer']);

            foreach ($lineItems as $lineItemEdge) {
                $lineItem = $lineItemEdge['node'];
                $productId = $lineItem['lineItem']['product']['id'];

                $assignedLineItems[] = [
                    'id' => $lineItem['id'],
                    'quantity' => $lineItem['remainingQuantity'],
                    'sku' => $lineItem['sku'],
                    'product_id' => $productId
                ];
            }

            data_set($fulfillmentOrder, 'line_items', $assignedLineItems);

            if ($shopifyUser->customer->is_fulfilment) {
                StoreFulfilmentFromShopify::run($shopifyUser, $fulfillmentOrder);
            } elseif ($shopifyUser->customer->is_dropshipping) {
                StoreOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
            }
        });
    }
}
