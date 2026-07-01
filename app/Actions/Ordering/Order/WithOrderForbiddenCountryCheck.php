<?php

/*
 * Author Louis Perez
 * Created on 01-07-2026-11h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Order;

use App\Models\Helpers\Address;
use App\Models\Ordering\Order;

trait WithOrderForbiddenCountryCheck
{
    public function isForbidden(Order $order)
    {
        $billingAddress     = $order->billingAddress;
        $deliveryAddress    = $order->deliveryAddress;
        
        $shop = $order->shop;
        $target = $shop;
        if (data_get($shop->settings, 'banned_countries.is_follow_organisation_banned_list', false)) {
            $target = $shop->organisation;
        };

        $bannedBillingCountries = $target->bannedBillingCountries();
        $bannedDeliveryCountries = $target->bannedDeliveryCountries();

        // If any of those is true, would be banned
        return $this->isAddressForbidden($billingAddress, $bannedBillingCountries) || $this->isAddressForbidden($deliveryAddress, $bannedDeliveryCountries);
    }

    public function isForbiddenDetailed(Order $order)
    {
        $billingAddress     = $order->billingAddress;
        $deliveryAddress    = $order->deliveryAddress;

        $shop = $order->shop;
        $target = $shop;
        if (data_get($shop->settings, 'banned_countries.is_follow_organisation_banned_list', false)) {
            $target = $shop->organisation;
        };

        $bannedBillingCountries = $target->bannedBillingCountries();
        $bannedDeliveryCountries = $target->bannedDeliveryCountries();

        return [
            'billing'   => $this->isAddressForbidden($billingAddress, $bannedBillingCountries),
            'delivery'  => $this->isAddressForbidden($deliveryAddress, $bannedDeliveryCountries)
        ];
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

        // Ban entire country if doesn't have postcode regex
        if (!$postcodeRegex) {
            return true;
        }

        // Allow if country have postcodeRegex but address doesn't have postal code
        if (!$address->postal_code) {
            return false;
        }

        // 1 if regex match, 0 if regex don't match, false if it fails
        $result = @preg_match($postcodeRegex, $address->postal_code);

        // If Regex is invalid, allow order to be sent nonetheless
        if ($result === false) {
            return false;
        }

        return $result === 1;
    }
}
