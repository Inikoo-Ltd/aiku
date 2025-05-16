<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Clients;

use App\Actions\Retina\Dropshipping\Client\StoreRetinaClientFromPlatform;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class FetchRetinaCustomerClientFromWooCommerce extends RetinaAction
{
    use WithGeneratedShopifyAddress;

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $wooCommerceUser = $this->customer->wooCommerceUser;
        $customers = $wooCommerceUser->getWooCommerceCustomers();

        foreach ($customers as $customer) {
            $customer = $customer->toArray();
            $address = Arr::get($customer, 'default_address', []);
            $existsClient = $this->customer->clients()->where('email', $customer['email'])->first();

            $attributes = $this->getAttributes($customer, $address);

            if (blank($address)) {
                data_set($attributes, 'address', $wooCommerceUser->customer?->deliveryAddress?->toArray());
            }

            StoreRetinaClientFromPlatform::run($wooCommerceUser, $attributes, $customer, $existsClient);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle();
    }

    public function inPlatform(Platform $platform, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle();
    }

    public function inPupil(Platform $platform, ActionRequest $request): void
    {
        $this->initialisationFromPupil($request);

        $this->handle();
    }
}
