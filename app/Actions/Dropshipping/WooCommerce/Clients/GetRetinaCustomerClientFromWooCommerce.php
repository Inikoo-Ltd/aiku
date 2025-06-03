<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Clients;

use App\Actions\Retina\Dropshipping\Client\StoreRetinaClientFromPlatformUser;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaCustomerClientFromWooCommerce extends RetinaAction
{
    use WithGeneratedWooCommerceAddress;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $customers = $wooCommerceUser->getWooCommerceCustomers();

        foreach ($customers as $customer) {
            $address = Arr::get($customer, 'shipping', []);
            $existsClient = $this->customer->clients()
                ->where('email', $customer['email'])
                ->where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
                ->first();

            $attributes = $this->getAttributes($address);

            if (blank($address)) {
                data_set($attributes, 'address', $wooCommerceUser->customer?->deliveryAddress?->toArray());
            }

            StoreRetinaClientFromPlatformUser::run($wooCommerceUser, $attributes, $customer, $existsClient);
        }
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->platform->type !== PlatformTypeEnum::WOOCOMMERCE) {
            $validator->errors()->add('platform', 'The platform type must be WooCommerce.');
        }

    }


    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;
        $this->handle($wooCommerceUser);
    }


}
