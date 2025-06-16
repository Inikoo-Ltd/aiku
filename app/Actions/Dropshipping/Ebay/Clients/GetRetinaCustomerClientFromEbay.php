<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Clients;

use App\Actions\Retina\Dropshipping\Client\StoreRetinaClientFromPlatformUser;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedEbayAddress;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaCustomerClientFromEbay extends RetinaAction
{
    use WithGeneratedEbayAddress;

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser): void
    {
        $customers = $ebayUser->getCustomers();

        foreach ($customers as $customer) {
            $address = Arr::get($customer, 'address', []);
            $existsClient = $this->customer->clients()
                ->where('email', $customer['email'])
                ->where('customer_sales_channel_id', $ebayUser->customer_sales_channel_id)
                ->first();

            $attributes = $this->getAttributes($address);

            if (blank($address)) {
                data_set($attributes, 'address', $ebayUser->customer?->deliveryAddress?->toArray());
            }

            StoreRetinaClientFromPlatformUser::run($ebayUser, $attributes, $customer, $existsClient);
        }
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->platform->type !== PlatformTypeEnum::EBAY) {
            $validator->errors()->add('platform', 'The platform type must be eBay.');
        }

    }


    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);
        /** @var EbayUser $ebayUser */
        $ebayUser = $customerSalesChannel->user;
        $this->handle($ebayUser);
    }


}
