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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CatchFulfilmentOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        DB::transaction(function () use ($shopifyUser, $modelData) {
            $client = $shopifyUser->api()->getRestClient();
            $orderId = Arr::get($modelData, 'id');
            $response = $client->request('GET', "/admin/api/2024-04/orders/$orderId/fulfillment_orders.json");

            foreach (Arr::get($response, 'body')['fulfillment_orders'] as $fulfilment) {
                $fulfilmentOrder = array_replace($fulfilment['container'], [
                    'line_items' => $modelData['line_items']
                ]);

                $fulfilmentOrder = array_merge($fulfilmentOrder, Arr::only($modelData, ['customer', 'shipping_address']));

                if ($shopifyUser->customer?->shop?->type === ShopTypeEnum::FULFILMENT) {
                    StoreFulfilmentFromShopify::run($shopifyUser, $fulfilmentOrder);
                } elseif ($shopifyUser->customer?->shop?->type === ShopTypeEnum::DROPSHIPPING) {
                    StoreOrderFromShopify::run($shopifyUser, $fulfilmentOrder);
                }
            }
        });
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        if(!$shopifyUser->customer_id ){
            abort(422);
        }

        $this->initialisation($shopifyUser->organisation, $request);

        $this->handle($shopifyUser, $request->all());
    }
}
