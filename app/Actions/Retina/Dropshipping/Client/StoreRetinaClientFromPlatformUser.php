<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;

class StoreRetinaClientFromPlatformUser extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser|TiktokUser $parent, array $attributes, array $customer, ?CustomerClient $existsClient): CustomerClient
    {
        data_set($attributes, 'customer_sales_channel_id', $parent->customerSalesChannel->id);
        if (!$existsClient) {
            $customerClient = StoreCustomerClient::make()->action($parent->customerSalesChannel, $attributes);

            AttachRetinaPlatformCustomerClient::run($parent->customer, $parent, [
                'customer_client_id' => $customerClient->id,
                'platform_customer_client_id' => Arr::get($customer, 'id')
            ]);
        } else {
            if (!$parent->clients()->where('customer_client_id', $existsClient->id)->exists()) {
                AttachRetinaPlatformCustomerClient::run($parent->customer, $parent, [
                    'customer_client_id' => $existsClient->id,
                    'platform_customer_client_id' => Arr::get($customer, 'id')
                ]);
            }

            if ($parent instanceof TiktokUser) {
                $attributes = Arr::except($attributes, 'address');
            }

            $customerClient = UpdateCustomerClient::run($existsClient, $attributes);
        }

        if ($customerClient->customer_id && $customerClient->platform_id) {
            $customerSalesChannel = CustomerSalesChannel::where('customer_id', $customerClient->customer_id)
                ->where('platform_id', $customerClient->platform_id)
                ->first();

            CustomerSalesChannelsHydrateCustomerClients::dispatch($customerSalesChannel);
        }

        return $customerClient;
    }
}
