<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): EbayUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY->value)->first();


        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'name', $customer->name); // For store only, later will updated in callback
        data_set($modelData, 'platform_id', $platform->id);

        /** @var EbayUser $ebayUser */
        $ebayUser = $customer->ebayUser()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($ebayUser),
            'platform_user_id' => $ebayUser->id,
            'reference' => $ebayUser->name,
            'name' => $ebayUser->name
        ]);

        $ebayUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        return $ebayUser;
    }
}
