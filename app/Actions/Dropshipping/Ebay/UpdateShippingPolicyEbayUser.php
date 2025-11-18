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
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateShippingPolicyEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser, array $modelData): EbayUser
    {
        $fulfillmentPolicyId = $ebayUser->fulfilment_policy_id;
        $fulfillmentPolicy = $ebayUser->updateFulfilmentPolicy($fulfillmentPolicyId, $modelData);

        if ($fulfillmentPolicy) {
            data_set($modelData, 'data.fulfillment_policy', $fulfillmentPolicy);
        }

        UpdateEbayUser::run($ebayUser, $modelData);

        return $ebayUser;
    }
}
