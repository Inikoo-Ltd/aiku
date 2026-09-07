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
        if ($ebayUser->fulfillment_policy_id && $ebayUser->return_policy_id && $ebayUser->payment_policy_id && $ebayUser->hasUsableLocationKey()) {
            return $ebayUser;
        }

        /** @var Shop $shop */
        $shop = $ebayUser->customer?->shop;

        $ebayUser->createOptInProgram();

        $fulfilmentPolicyId = $this->storedPolicyStillOnEbay($ebayUser->fulfillment_policy_id, fn () => $ebayUser->getUsableFulfilmentPolicyIds()->all())
            ?? Arr::get($ebayUser->createFulfilmentPolicy([]), 'fulfillmentPolicyId')
            ?? $ebayUser->getUsableFulfilmentPolicyId();

        $paymentPolicyId = $this->storedPolicyStillOnEbay($ebayUser->payment_policy_id, fn () => Arr::pluck(Arr::get($ebayUser->getPaymentPolicies(), 'paymentPolicies', []), 'paymentPolicyId'))
            ?? Arr::get($ebayUser->createPaymentPolicy(), 'paymentPolicyId')
            ?? Arr::get($ebayUser->getPaymentPolicies(), 'paymentPolicies.0.paymentPolicyId');

        $returnPolicyId = $this->storedPolicyStillOnEbay($ebayUser->return_policy_id, fn () => Arr::pluck(Arr::get($ebayUser->getReturnPolicies(), 'returnPolicies', []), 'returnPolicyId'))
            ?? Arr::get($ebayUser->createReturnPolicy(), 'returnPolicyId')
            ?? Arr::get($ebayUser->getReturnPolicies(), 'returnPolicies.0.returnPolicyId');

        $country = Country::find(Arr::get($shop?->settings, 'ebay.warehouse_country'));

        $defaultLocationData = [
            'locationKey' => $shop->slug . '-warehouse-' . $country->code,
            'city' => Arr::get($shop?->settings, 'ebay.warehouse_city'),
            'state' => Arr::get($shop?->settings, 'ebay.warehouse_state'),
            'country' => $country->code
        ];

        return UpdateEbayUser::run($ebayUser, [
            'fulfillment_policy_id' => $fulfilmentPolicyId,
            'payment_policy_id' => $paymentPolicyId,
            'return_policy_id' => $returnPolicyId,
            'location_key' => $this->provisionLocationKey($ebayUser, $defaultLocationData),
        ]);
    }

    /**
     * A policy the seller customised through the channel page lives on its id, so re-provisioning must not
     * replace it. The id is only kept when eBay still lists it, otherwise the policy is created as before.
     *
     * @param  callable(): array<int, string>  $policyIdsOnEbay
     */
    private function storedPolicyStillOnEbay(?string $storedPolicyId, callable $policyIdsOnEbay): ?string
    {
        if (blank($storedPolicyId)) {
            return null;
        }

        return in_array($storedPolicyId, $policyIdsOnEbay(), true) ? $storedPolicyId : null;
    }

    /**
     * A key is only written when eBay accepted the location or already has it. A refusal never wipes a key
     * that was usable before, since an outage on eBay's side is not evidence the location is gone.
     */
    private function provisionLocationKey(EbayUser $ebayUser, array $locationData): ?string
    {
        $locationKey = Arr::get($locationData, 'locationKey');
        $response    = $ebayUser->createInventoryLocation($locationData);

        if (!is_array($response) || !Arr::hasAny($response, ['error', 'errors'])) {
            return $locationKey;
        }

        if ($this->saysLocationAlreadyExists($response) || $this->hasLocationOnEbay($ebayUser, $locationKey)) {
            return $locationKey;
        }

        return $ebayUser->hasUsableLocationKey() ? $ebayUser->location_key : null;
    }

    private function saysLocationAlreadyExists(array $response): bool
    {
        return collect(Arr::get($response, 'errors', []))
            ->contains(fn ($error) => str_contains(strtolower((string) Arr::get($error, 'message')), 'already exist'));
    }

    private function hasLocationOnEbay(EbayUser $ebayUser, string $locationKey): bool
    {
        $locations = $ebayUser->getInventoryLocations();

        if (!is_array($locations) || Arr::hasAny($locations, ['error', 'errors'])) {
            return false;
        }

        return collect(Arr::get($locations, 'locations', []))
            ->pluck('merchantLocationKey')
            ->contains($locationKey);
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user);
    }
}
