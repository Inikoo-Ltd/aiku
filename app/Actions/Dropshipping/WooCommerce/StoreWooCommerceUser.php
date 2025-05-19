<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): WooCommerceUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE->value)->first();


        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'name', Arr::get($modelData, 'name'));
        data_set($modelData, 'settings.credentials.consumer_key', Arr::pull($modelData, 'consumer_key'));
        data_set($modelData, 'settings.credentials.consumer_secret', Arr::pull($modelData, 'consumer_secret'));
        data_set($modelData, 'settings.credentials.store_url', Arr::pull($modelData, 'store_url'));
        data_set($modelData, 'platform_id', $platform->id);

        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customer->wooCommerceUser()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($wooCommerceUser),
            'platform_user_id' => $wooCommerceUser->id,
        ]);
        $wooCommerceUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);
        return $wooCommerceUser;


    }
}
