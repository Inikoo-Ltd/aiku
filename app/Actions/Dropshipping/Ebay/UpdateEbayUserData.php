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
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Helpers\Country;
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

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser): EbayUser
    {
        if ($ebayUser->fulfillment_policy_id && $ebayUser->return_policy_id && $ebayUser->payment_policy_id && $ebayUser->location_key) {
            return $ebayUser;
        }

        /** @var Shop $shop */
        $shop = $ebayUser->customer?->shop;

        $ebayUser->createOptInProgram();
        $ebayUser->createFulfilmentPolicy([]);
        $ebayUser->createPaymentPolicy();
        $ebayUser->createReturnPolicy();

        $fulfilmentPolicies = $ebayUser->getFulfilmentPolicies();
        $fulfilmentPolicyId = Arr::get($fulfilmentPolicies, 'fulfillmentPolicies.0.fulfillmentPolicyId');

        $paymentPolicies = $ebayUser->getPaymentPolicies();
        $paymentPolicyId = Arr::get($paymentPolicies, 'paymentPolicies.0.paymentPolicyId');

        $returnPolicies = $ebayUser->getReturnPolicies();
        $returnPolicyId = Arr::get($returnPolicies, 'returnPolicies.0.returnPolicyId');

        $country = Country::find(Arr::get($shop?->settings, 'ebay.warehouse_country'));

        $defaultLocationData = [
            'locationKey' => $shop->slug . '-warehouse-' . $country->code,
            'city' => Arr::get($shop?->settings, 'ebay.warehouse_city'),
            'state' => Arr::get($shop?->settings, 'ebay.warehouse_state'),
            'country' => $country->code
        ];

        $ebayUser->createInventoryLocation($defaultLocationData);

        return UpdateEbayUser::run($ebayUser, [
            'fulfillment_policy_id' => $fulfilmentPolicyId,
            'payment_policy_id' => $paymentPolicyId,
            'return_policy_id' => $returnPolicyId,
            'location_key' => Arr::get($defaultLocationData, 'locationKey'),
        ]);
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user);
    }
}
