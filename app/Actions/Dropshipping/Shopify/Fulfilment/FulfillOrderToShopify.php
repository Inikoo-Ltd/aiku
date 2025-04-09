<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Feb 2025 16:53:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $fulfillOrderId = $order->shopifyOrder?->shopify_fulfilment_id;
        $shopifyUser = $order->shopifyOrder?->shopifyUser;

        $response = $shopifyUser->api()->getRestClient()->request('POST', 'admin/api/2024-07/fulfillments.json', [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => [
                    [
                        'fulfillment_order_id' => $fulfillOrderId
                    ]
                ]
            ]
        ]);

        if (!$response['errors']) {
            $this->update($order->shopifyOrder, [
                'state' => ChannelFulfilmentStateEnum::DISPATCHED
            ]);
        }

        if ($response['body'] == 'Not Found') {
            throw ValidationException::withMessages(['messages' => __('You dont have any products')]);
        }
    }
}
