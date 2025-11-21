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

class UpdateReturnPolicyEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser, array $modelData): EbayUser
    {
        $customerSalesChannel = $ebayUser->customerSalesChannel;

        data_set($modelData, 'settings.return.accepted', Arr::get($customerSalesChannel->settings, 'return.accepted'));
        data_set($modelData, 'settings.return.payer', Arr::get($customerSalesChannel->settings, 'return.payer'));
        data_set($modelData, 'settings.return.within', Arr::get($customerSalesChannel->settings, 'return.within'));
        data_set($modelData, 'settings.return.description', Arr::get($customerSalesChannel->settings, 'return.description'));

        $returnPolicyId = Arr::get($modelData, 'return_policy_id', $ebayUser->return_policy_id);
        $returnPolicy = $ebayUser->updateReturnPolicy($returnPolicyId, $modelData);

        if (! Arr::has($returnPolicy, 'errors')) {
            data_set($modelData, 'data.return_policy', $returnPolicy);
        }

        UpdateEbayUser::run($ebayUser, $modelData);

        return $ebayUser;
    }
}
