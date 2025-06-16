<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Feb 2025 15:06:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ShopifyFulfilmentReasonEnum;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasFulfilment;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HoldFulfilmentOrderShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUserHasFulfilment $shopifyUserHasFulfilment, ShopifyUser $shopifyUser): void
    {
        $client = $shopifyUser->api()->getRestClient();
        $response = $client->request('POST', "/admin/api/2024-04/fulfillment_orders/$shopifyUserHasFulfilment->shopify_fulfilment_id/hold.json", [
            'fulfillment_hold' => [
                'reason' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->value,
                'reason_notes' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->notes()[ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->value]
            ]
        ]);

        if ($response['body'] == 'Not Found') {
            throw ValidationException::withMessages(['messages' => __('You dont have any products')]);
        }
    }
}
