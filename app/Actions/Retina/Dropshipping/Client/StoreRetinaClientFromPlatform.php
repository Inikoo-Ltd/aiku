<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateCustomerClients;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\CustomerSalesChannel;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;

class StoreRetinaClientFromPlatform extends RetinaAction
{
    public function handle(ShopifyUser|TiktokUser $parent, array $attributes, array $customer, ?CustomerClient $existsClient): CustomerClient
    {
        $platform = Platform::query();
        data_set($attributes, 'platform_id', match (class_basename($parent)) {
            'ShopifyUser' => $platform->where('type', PlatformTypeEnum::SHOPIFY)->first()->id,
            'TiktokUser' => $platform->where('type', PlatformTypeEnum::TIKTOK)->first()->id,
            default => $platform->where('type', PlatformTypeEnum::MANUAL)->first()->id
        });

        if (!$existsClient) {
            $customerClient = StoreCustomerClient::make()->action($parent->customer, $attributes);

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
            $customerHasPlatform = CustomerSalesChannel::where('customer_id', $customerClient->customer_id)
            ->where('platform_id', $customerClient->platform_id)
            ->first();

            CustomerHasPlatformsHydrateCustomerClients::dispatch($customerHasPlatform);
        }

        return $customerClient;
    }
}
