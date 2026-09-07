<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Clients;

use App\Actions\Dropshipping\WooCommerce\Product\CheckIfProductExistInWoo;
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
     * A store customer carries the email at the top level and in billing, never in shipping. A client
     * is recognised by phone, and by name among the phone-less ones, so a customer without a phone
     * never lands on somebody else's row. An address the country table cannot place falls back to
     * the customer's own delivery address rather than being saved without a country.
     *
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $customers = CheckIfProductExistInWoo::onlyProducts($wooCommerceUser->getWooCommerceCustomers());

        foreach ($customers as $customer) {
            $address = array_filter(Arr::get($customer, 'shipping', []));
            $phone   = Arr::get($customer, 'shipping.phone') ?: Arr::get($customer, 'billing.phone');

            $attributes          = $this->getAttributes($address);
            $attributes['email'] = Arr::get($customer, 'email') ?: Arr::get($customer, 'billing.email', '');
            $attributes['phone'] = $phone;

            $clients = $wooCommerceUser->customer->clients()
                ->where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id);

            $existsClient = (filled($phone) ? (clone $clients)->where('phone', $phone)->first() : null)
                ?? (clone $clients)->whereNull('phone')->where('contact_name', $attributes['contact_name'])->first();

            if (blank($address) || !Arr::get($attributes, 'address.country_id')) {
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
