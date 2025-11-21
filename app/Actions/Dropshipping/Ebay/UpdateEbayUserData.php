<?php

/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-16h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateEbayUserData extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'update:ebay {customerSalesChannel}';

    public function handle(EbayUser $ebayUser): EbayUser
    {
        $ebayUser->createOptInProgram();
        $fulfilment = $ebayUser->createFulfilmentPolicy([]);
        $payment = $ebayUser->createPaymentPolicy();
        $return = $ebayUser->createReturnPolicy();

        $defaultLocationData = match (Arr::get($ebayUser->customer?->shop?->settings, 'ebay.marketplace_id', 'EBAY_GB')) {
            'EBAY_ES' => [
                'locationKey' => 'esWarehouse',
                'city' => 'Zavar',
                'state' => 'Trnava Region',
                'country' => 'ES',
            ],
            default => [
                'locationKey' => 'mainWarehouse',
                'city' => 'Sheffield',
                'state' => 'England',
                'country' => 'GB',
            ]
        };

        $ebayUser->createInventoryLocation($defaultLocationData);


        return UpdateEbayUser::run($ebayUser, [
            'fulfillment_policy_id' => Arr::get($fulfilment, 'fulfillmentPolicyId'),
            'payment_policy_id' => Arr::get($payment, 'paymentPolicyId'),
            'return_policy_id' => Arr::get($return, 'returnPolicyId'),
            'location_key' => Arr::get($defaultLocationData, 'locationKey'),
        ]);
    }

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user);
    }
}
