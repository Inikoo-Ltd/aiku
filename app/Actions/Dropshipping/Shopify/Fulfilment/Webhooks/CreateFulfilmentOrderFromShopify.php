<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks;

use App\Actions\Dropshipping\Shopify\Order\StoreFulfilmentOrderFromShopify;
use App\Actions\Dropshipping\Shopify\Order\StoreOrderFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CreateFulfilmentOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * A line item with no product, and an order with no usable line item at all, are both carried
     * through rather than dropped (HELP-3151): the store action turns them into notes on the order
     * so the office can see what Shopify sent. Only a line item with nothing left to fulfil is
     * skipped, since that quantity has already been shipped.
     *
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $fulfillmentOrder): void
    {
        $assignedLineItems = [];

        $destination = Arr::get($fulfillmentOrder, 'destination', []);
        $lineItems = Arr::get($fulfillmentOrder, 'lineItems.edges', []);

        data_set($fulfillmentOrder, 'shipping_address', $destination);
        data_set($fulfillmentOrder, 'customer', $fulfillmentOrder['order']['customer']);
        data_set($fulfillmentOrder, 'created_at', $fulfillmentOrder['order']['createdAt']);
        data_set($fulfillmentOrder, 'placed_at', $fulfillmentOrder['order']['processedAt'] ?? null);

        foreach ($lineItems as $lineItemEdge) {
            $lineItem = $lineItemEdge['node'];

            $assignedLineItems[] = [
                'id' => $lineItem['id'],
                'quantity' => $lineItem['remainingQuantity'],
                'sku' => Arr::get($lineItem, 'sku'),
                'title' => Arr::get($lineItem, 'productTitle'),
                'product_id' => data_get($lineItem, 'lineItem.product.id'),
                'product_variant_id' => data_get($lineItem, 'lineItem.variant.id')
            ];
        }

        data_set($fulfillmentOrder, 'line_items', $assignedLineItems);

        if ($shopifyUser->customer->is_dropshipping) {
            StoreOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
        } elseif ($shopifyUser->customer->is_fulfilment) {
            StoreFulfilmentOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
        }
    }
}
