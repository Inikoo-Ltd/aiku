<?php

/*
 * Author Louis Perez
 * Created on 01-07-2026-11h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Order;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithOrderForbiddenCountryCheck
{
    public function isForbidden(Order $order): bool
    {
        $data = $this->getBannedCountriesData($order);

        return $this->isAddressForbidden($data['billingAddress'], $data['bannedBillingCountries']) || $this->isAddressForbidden($data['deliveryAddress'], $data['bannedDeliveryCountries'], (bool) $order->collection_address_id);
    }

    public function isForbiddenDetailed(Order $order): array
    {
        $data = $this->getBannedCountriesData($order);

        return [
            'billing'  => $this->isAddressForbidden($data['billingAddress'], $data['bannedBillingCountries']),
            'delivery' => $this->isAddressForbidden($data['deliveryAddress'], $data['bannedDeliveryCountries'], (bool) $order->collection_address_id),
        ];
    }

    public function isDeliveryForbiddenForShop(Shop $shop, ?Address $deliveryAddress): bool
    {
        return $this->isAddressForbidden($deliveryAddress, $this->getBannedCountriesTarget($shop)->bannedDeliveryCountries());
    }

    /**
     * A channel order to a country the shop does not deliver to is stopped where it arrives, and
     * what stopped it is kept on the order: the banned list can change later, and this is the only
     * record of why the order was held or declined (HELP-4392).
     *
     * @return array{country_code: string, postal_code: ?string, reason: string, at: string}|null
     */
    public function getBannedDelivery(Shop $shop, ?Address $deliveryAddress): ?array
    {
        $bannedCountries = $this->getBannedCountriesTarget($shop)->bannedDeliveryCountries();

        if (!$this->isAddressForbidden($deliveryAddress, $bannedCountries)) {
            return null;
        }

        $bannedDelivery = [
            'country_code' => $deliveryAddress->country_code,
            'postal_code'  => $deliveryAddress->postal_code,
            'by_postcode'  => filled(data_get($bannedCountries, $deliveryAddress->country_code.'.postcode')),
            'at'           => now()->toIso8601String(),
        ];

        return $bannedDelivery + ['reason' => $this->bannedDeliveryReason($bannedDelivery)];
    }

    /**
     * Built again wherever it is shown, so an email in the shop's language does not carry the
     * English sentence stored when the order was imported.
     */
    public function bannedDeliveryReason(array $bannedDelivery): string
    {
        $country = Country::where('code', $bannedDelivery['country_code'])->value('name') ?? $bannedDelivery['country_code'];

        return Arr::get($bannedDelivery, 'by_postcode')
            ? __('We do not deliver to postcode :postcode in :country.', ['postcode' => $bannedDelivery['postal_code'], 'country' => $country])
            : __('We do not deliver to :country.', ['country' => $country]);
    }

    protected function getBannedCountriesData(Order $order): array
    {
        $target = $this->getBannedCountriesTarget($order->shop);

        return [
            'billingAddress'          => $order->billingAddress,
            'deliveryAddress'         => $order->deliveryAddress,
            'bannedBillingCountries'  => $target->bannedBillingCountries(),
            'bannedDeliveryCountries' => $target->bannedDeliveryCountries(),
        ];
    }

    protected function getBannedCountriesTarget(Shop $shop): Shop|Organisation
    {
        if (data_get($shop->settings, 'banned_countries.is_follow_organisation_banned_list', false)) {
            return $shop->organisation;
        }

        return $shop;
    }

    public function isAddressForbidden(?Address $address, array $bannedCountries, bool $skipCheck = false): bool
    {
        // For is_collection
        if ($skipCheck) {
            return false;
        }

        // Allow if no address is present; without a country code data_get below would return the whole list
        if (!$address || blank($address->country_code)) {
            return false;
        }

        $bannedCountry = data_get($bannedCountries, $address->country_code);

        // Allow if country isn't in the list
        if (!$bannedCountry) {
            return false;
        }

        $postcodeRegex = data_get($bannedCountry, 'postcode');

        // Ban entire country if it doesn't have postcode regex
        if (!$postcodeRegex) {
            return true;
        }

        // Allow if country has postcodeRegex but address doesn't have postal code
        if (!$address->postal_code) {
            return false;
        }

        // 1 if regex matches, 0 if regex doesn't match, false if it fails
        $result = @preg_match($postcodeRegex, $address->postal_code);

        // If Regex is invalid, allow order to be sent nonetheless
        if ($result === false) {
            return false;
        }

        return $result === 1;
    }
}
