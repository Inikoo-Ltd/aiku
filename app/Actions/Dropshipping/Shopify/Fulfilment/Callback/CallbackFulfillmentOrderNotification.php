<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment\Callback;

use App\Actions\Dropshipping\Shopify\Fulfilment\Webhooks\CatchFulfilmentOrderFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackFulfillmentOrderNotification extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        if (Arr::has($modelData, 'fulfillment_order') && Arr::get($modelData, 'kind') === "FULFILLMENT_REQUEST") {
            CatchFulfilmentOrderFromShopify::run($shopifyUser, [
                'id' => Arr::get($modelData, 'fulfillment_order.order_id'),
                'fulfillment_status' => Arr::get($modelData, 'fulfillment_order.status'),
                'line_items' => Arr::get($modelData, 'fulfillment_order.line_items')
            ]);
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        if (!$shopifyUser->customer_id) {
            abort(422);
        }

        $this->initialisation($shopifyUser->organisation, $request);

        $this->handle($shopifyUser, $request->all());
    }
}
