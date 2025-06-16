<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Feb 2025 16:53:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasFulfilment;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShopifyOrderFulfilment extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedShopifyAddress;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, PalletReturn $model, array $modelData)
    {
        $customer       = Arr::get($modelData, 'customer');
        $address        = Arr::get($customer, 'default_address');
        $customerClient = $shopifyUser->customer->clients()->where('email', Arr::get($customer, 'email'))->first();

        if (!$customerClient) {
            $attributes = $this->getAttributes($customer, $address);
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customerSalesChannel, $attributes);
        }

        $shopifyUser->orders()->attach($model->id, [
            'shopify_user_id'            => $shopifyUser->id,
            'model_type'                 => class_basename($model),
            'model_id'                   => $model->id,
            'shopify_order_id'           => Arr::get($modelData, 'shopify_order_id'),
            'shopify_fulfilment_id'      => Arr::get($modelData, 'shopify_fulfilment_id'),
            'state'                      => Arr::get($modelData, 'state'),
            'no_fulfilment_reason'       => Arr::get($modelData, 'no_fulfilment_reason'),
            'no_fulfilment_reason_notes' => Arr::get($modelData, 'no_fulfilment_reason_notes'),
            'customer_client_id'         => $customerClient->id
        ]);

        return ShopifyUserHasFulfilment::where('shopify_fulfilment_id', Arr::get($modelData, 'shopify_fulfilment_id'))->first();
    }
}
