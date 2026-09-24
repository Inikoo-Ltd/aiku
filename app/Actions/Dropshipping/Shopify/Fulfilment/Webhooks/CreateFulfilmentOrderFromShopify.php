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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CreateFulfilmentOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 15;

    /**
     * The webhook, the poller and a retry can reach the same fulfilment order at once, and the order
     * is only looked up before it is created, so one of them would create and charge it twice.
     *
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $fulfillmentOrder): void
    {
        $lockKey = 'shopify_fulfilment_order_'.$shopifyUser->id.'_'.Arr::get($fulfillmentOrder, 'id');

        try {
            Cache::lock($lockKey, self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, fn () => $this->createOrder($shopifyUser, $fulfillmentOrder));
        } catch (LockTimeoutException) {
            Log::warning('Shopify fulfilment order skipped, another import held '.$lockKey.' for over '.self::LOCK_WAIT_SECONDS.' seconds; fetch the channel orders again if it is missing from AW.');
        }
    }

    /**
     * @throws \Throwable
     */
    private function createOrder(ShopifyUser $shopifyUser, array $fulfillmentOrder): void
    {
        $assignedLineItems = [];

        $destination = $fulfillmentOrder['destination'];
        $lineItems = $fulfillmentOrder['lineItems']['edges'];

        data_set($fulfillmentOrder, 'shipping_address', $destination);
        data_set($fulfillmentOrder, 'customer', $fulfillmentOrder['order']['customer']);
        data_set($fulfillmentOrder, 'created_at', $fulfillmentOrder['order']['createdAt']);
        data_set($fulfillmentOrder, 'placed_at', $fulfillmentOrder['order']['processedAt'] ?? null);

        foreach ($lineItems as $lineItemEdge) {
            $lineItem = $lineItemEdge['node'];

            $productId = data_get($lineItem, 'lineItem.product.id');
            $productVariantId = data_get($lineItem, 'lineItem.variant.id');

            if (empty($productId) && empty($productVariantId) && blank($lineItem['sku'] ?? null)) {
                continue;
            }

            if ($lineItem['remainingQuantity'] <= 0) {
                continue;
            }

            $assignedLineItems[] = [
                'id' => $lineItem['id'],
                'quantity' => $lineItem['remainingQuantity'],
                'sku' => $lineItem['sku'],
                'product_id' => $productId,
                'product_variant_id' => $productVariantId
            ];
        }

        if (empty($assignedLineItems) && !Arr::has($fulfillmentOrder, 'declined_reason')) {
            return;
        }

        data_set($fulfillmentOrder, 'line_items', $assignedLineItems);

        if ($shopifyUser->customer->is_dropshipping) {
            StoreOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
        } elseif ($shopifyUser->customer->is_fulfilment) {
            StoreFulfilmentOrderFromShopify::run($shopifyUser, $fulfillmentOrder);
        }
    }
}
