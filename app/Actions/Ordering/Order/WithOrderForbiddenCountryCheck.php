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
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;

trait WithOrderForbiddenCountryCheck
{
    public function isForbidden(Order $order): bool
    {
        $data = $this->getBannedCountriesData($order);

        return $this->isAddressForbidden($data['billingAddress'], $data['bannedBillingCountries']) || $this->isAddressForbidden($data['deliveryAddress'], $data['bannedDeliveryCountries']);
    }

    public function isForbiddenDetailed(Order $order): array
    {
        $data = $this->getBannedCountriesData($order);

        return [
            'billing'  => $this->isAddressForbidden($data['billingAddress'], $data['bannedBillingCountries']),
            'delivery' => $this->isAddressForbidden($data['deliveryAddress'], $data['bannedDeliveryCountries']),
        ];
    }

    public function isDeliveryForbiddenForShop(Shop $shop, ?Address $deliveryAddress): bool
    {
        return $this->isAddressForbidden($deliveryAddress, $this->getBannedCountriesTarget($shop)->bannedDeliveryCountries());
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

    public function isAddressForbidden(?Address $address, array $bannedCountries): bool
    {
        // Allow if no address is present
        if (!$address) {
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
