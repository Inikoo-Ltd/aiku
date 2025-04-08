<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Feb 2025 15:06:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DispatchFulfilmentOrderShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(PalletReturn $palletReturn): void
    {
        $shopifyUserHasFulfilment = $palletReturn->shopifyFulfilment;
        $shopifyUser = $shopifyUserHasFulfilment->shopifyUser;
        $client = $shopifyUser->api()->getRestClient();

        $response = $client->request('POST', "/admin/api/2024-04/fulfillments", [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => [
                    [
                        'fulfillment_order_id' => $shopifyUserHasFulfilment->shopify_fulfilment_id
                    ]
                ]
            ]
        ]);

        if (!$response['errors']) {
            $this->update($shopifyUserHasFulfilment, [
                'state' => ChannelFulfilmentStateEnum::DISPATCHED
            ]);
        }

        if ($response['body'] == 'Not Found') {
            throw ValidationException::withMessages(['messages' => __('You dont have any products')]);
        }
    }
}
