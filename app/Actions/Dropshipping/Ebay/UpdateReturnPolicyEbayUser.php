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
use Illuminate\Validation\ValidationException;
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

        foreach (['accepted', 'payer', 'within', 'description'] as $field) {
            data_set($modelData, "settings.return.$field", Arr::get($modelData, "settings.return.$field", Arr::get($customerSalesChannel->settings, "return.$field")));
        }

        $policies = $ebayUser->getReturnPolicies();

        if ($error = $ebayUser::ebayResponseError($policies)) {
            throw ValidationException::withMessages([
                'return_accepted' => __('eBay did not accept the return policy: :error', ['error' => $error]),
            ]);
        }

        $returnPolicyId = collect(Arr::get($policies, 'returnPolicies', []))
            ->firstWhere('name', $ebayUser->ownReturnPolicyName())['returnPolicyId'] ?? null;

        $returnPolicy = $returnPolicyId ? null : $ebayUser->createReturnPolicy();

        if (! $ebayUser::ebayResponseError($returnPolicy)) {
            $returnPolicy = $ebayUser->updateReturnPolicy($returnPolicyId ?? Arr::get($returnPolicy, 'returnPolicyId'), $modelData);
        }

        if ($error = $ebayUser::ebayResponseError($returnPolicy)) {
            throw ValidationException::withMessages([
                'return_accepted' => __('eBay did not accept the return policy: :error', ['error' => $error]),
            ]);
        }

        $updatedReturnPolicyId = Arr::get($returnPolicy, 'returnPolicyId');

        if ($updatedReturnPolicyId) {
            data_set($modelData, 'data.return_policy', $returnPolicy);
            data_set($modelData, 'return_policy_id', $updatedReturnPolicyId);
        }

        UpdateEbayUser::run($ebayUser, $modelData);

        return $ebayUser;
    }
}
