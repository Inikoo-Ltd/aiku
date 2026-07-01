<?php

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
        $bannedBillingCountries = $shop->bannedBillingCountries();
        $bannedDeliveryCountries = $shop->bannedDeliveryCountries();

        // If any of those is true, would be banned
        return $this->isAddressForbidden($billingAddress, $bannedBillingCountries) || $this->isAddressForbidden($deliveryAddress, $bannedDeliveryCountries);
    }

    public function isForbiddenDetailed(Order $order)
    {
        $billingAddress     = $order->billingAddress;
        $deliveryAddress    = $order->deliveryAddress;

        $shop = $order->shop;
        $bannedBillingCountries = $shop->bannedBillingCountries();
        $bannedDeliveryCountries = $shop->bannedDeliveryCountries();

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

        // Ban entire country if doesn't have postcode regex or if no postcode exists since we can't compare it anyway. Would need confirmation from Raul though
        if (!$postcodeRegex || !$address->postal_code) {
            return true;
        }

        return preg_match($postcodeRegex, $address->postal_code) === 1;
    }
}
