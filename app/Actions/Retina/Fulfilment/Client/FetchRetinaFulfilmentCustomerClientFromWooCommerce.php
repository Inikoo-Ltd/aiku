<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Client;

use App\Actions\Retina\Dropshipping\Client\StoreRetinaClientFromPlatformUser;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class FetchRetinaFulfilmentCustomerClientFromWooCommerce extends RetinaAction
{
    use WithGeneratedShopifyAddress;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $customers = $wooCommerceUser->getWooCommerceCustomers();

        foreach ($customers as $customer) {
            $customer = $customer->toArray();
            $address = Arr::get($customer, 'default_address', []);
            $existsClient = $this->customer->clients()->where('email', $customer['email'])->first();

            $attributes = $this->getAttributes($customer, $address);

            if (blank($address)) {
                data_set($attributes, 'address', $wooCommerceUser->customer?->deliveryAddress?->toArray());
            }

            StoreRetinaClientFromPlatformUser::run($wooCommerceUser, $attributes, $customer, $existsClient);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }


    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;
        $this->initialisation($request);

        $this->handle($wooCommerceUser);
    }
}
