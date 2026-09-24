<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 02:56:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\AcceptShopifyFulfillmentRequest;
use App\Actions\Dropshipping\Shopify\Fulfilment\Callback\SplitShopifyFulfillmentRequest;
use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CreateFulfilmentOrderFromShopify;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A request still waiting for our answer in Shopify takes the webhook's route, split then accept,
 * so an order recovered by the poller or a retry is also answered in Shopify. One already accepted
 * only needs the order in AW. A request AW already holds a live order for, left unanswered by the
 * old poller, is only accepted, or left alone when staff cancelled that order: splitting it again
 * could decline in Shopify goods AW has charged or shipped.
 */
class ImportShopifyFulfilmentOrder
{
    use AsObject;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $fulfilmentOrder): void
    {
        if (Arr::get($fulfilmentOrder, 'requestStatus') !== 'SUBMITTED') {
            CreateFulfilmentOrderFromShopify::run($shopifyUser, $fulfilmentOrder);

            return;
        }

        $existingOrder = Order::where('platform_order_id', Arr::get($fulfilmentOrder, 'id'))->first();

        if ($existingOrder && !$existingOrder->isDeclinedPlatformRequest()) {
            if ($existingOrder->state !== OrderStateEnum::CANCELLED) {
                AcceptShopifyFulfillmentRequest::run($shopifyUser, $fulfilmentOrder);
            }

            return;
        }

        $fulfilmentOrderRequested = SplitShopifyFulfillmentRequest::run($shopifyUser, $fulfilmentOrder);

        if (Arr::has($fulfilmentOrderRequested, 'id')) {
            AcceptShopifyFulfillmentRequest::run($shopifyUser, $fulfilmentOrderRequested);
        }
    }
}
