<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateShippingPolicyEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser, array $modelData): EbayUser
    {
        $customerSalesChannel = $ebayUser->customerSalesChannel;

        foreach (['price', 'max_dispatch_time', 'carrier_code', 'carrier_name', 'service_code', 'service_name'] as $shippingSetting) {
            $value = Arr::get($customerSalesChannel->settings, 'shipping.'.$shippingSetting);

            if (filled($value)) {
                data_set($modelData, 'settings.shipping.'.$shippingSetting, $value);
            }
        }

        $fulfillmentPolicyId = Arr::get($modelData, 'fulfillment_policy_id', $ebayUser->fulfillment_policy_id);
        $fulfillmentPolicy = $ebayUser->updateFulfilmentPolicy($fulfillmentPolicyId, $modelData);

        $updatedFulfillmentPolicyId = Arr::get($fulfillmentPolicy, 'fulfillmentPolicyId');

        if ($updatedFulfillmentPolicyId) {
            data_set($modelData, 'data.fulfillment_policy', $fulfillmentPolicy);
            data_set($modelData, 'fulfillment_policy_id', $updatedFulfillmentPolicyId);
        }

        UpdateEbayUser::run($ebayUser, $modelData);

        return $ebayUser;
    }
}
