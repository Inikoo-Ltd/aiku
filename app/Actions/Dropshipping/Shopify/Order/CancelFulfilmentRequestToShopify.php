<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUserHasFulfilment;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CancelFulfilmentRequestToShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn): void
    {
        $customer = $palletReturn->fulfilmentCustomer->customer;
        $shopifyUser = $customer->shopifyUser;

        /** @var ShopifyUserHasFulfilment $fulfilmentShopify */
        $fulfilmentShopify = $palletReturn->shopifyFulfilment;

        $client = $shopifyUser->api()->getRestClient();
        $response = $client->request('POST', "/admin/api/2024-04/fulfillment_orders/$fulfilmentShopify->shopify_fulfilment_id/cancellation_request.json", [
            'cancellation_request' => [
                'message' => $palletReturn->deleted_note
            ]
        ]);

        if ($response['status'] == 422) {
            abort($response['status'], $response['body']);
        }
    }
}
