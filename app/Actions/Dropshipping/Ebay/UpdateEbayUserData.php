<?php

/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-16h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateEbayUserData extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $queue = 'long-running';

    public function handle(EbayUser $ebayUser): EbayUser
    {
        $ebayUser->createOptInProgram();
        $ebayUser->createFulfilmentPolicy();
        $ebayUser->createPaymentPolicy();
        $ebayUser->createReturnPolicy();
        $defaultLocationData = [
            'locationKey' => 'mainWarehouse',
            'city' => 'Sheffield',
            'state' => 'England',
            'country' => 'GB',
        ];
        $ebayUser->createInventoryLocation($defaultLocationData);

        $fulfilmentPolicies = $ebayUser->getFulfilmentPolicies();
        $paymentPolicies = $ebayUser->getPaymentPolicies();
        $returnPolicies = $ebayUser->getReturnPolicies();
        $userData = $ebayUser->getUser();

        $updatedSettings = [
            ...$ebayUser->settings,
            'defaults' => [
                'main_location_key' => Arr::get($defaultLocationData, 'locationKey'),
                'main_fulfilment_policy_id' => Arr::get($fulfilmentPolicies, 'fulfillmentPolicies.0.fulfillmentPolicyId'),
                'main_payment_policy_id' => Arr::get($paymentPolicies, 'paymentPolicies.0.paymentPolicyId'),
                'main_return_policy_id' => Arr::get($returnPolicies, 'returnPolicies.0.returnPolicyId'),
            ]
        ];


        $ebayUser = UpdateEbayUser::run($ebayUser, [
            'name' => Arr::get($userData, 'username'),
            'settings' => $updatedSettings
        ]);

        UpdateCustomerSalesChannel::run($ebayUser->customerSalesChannel, [
            'reference' => Arr::get($userData, 'username'),
            'name' => Arr::get($userData, 'username'),
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        $ebayUser->refresh();

        return $ebayUser;
    }
}
