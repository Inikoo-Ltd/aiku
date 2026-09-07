<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

class StoreCustomerFromShopify extends OrgAction
{
    public function handle(Shop $shop, array $shopifyCustomer, ?array $fallbackAddress = null): Customer
    {
        $customerId = str_replace('gid://shopify/Customer/', '', Arr::get($shopifyCustomer, 'id'));

        $customer = Customer::where('shop_id', $shop->id)
            ->where('external_id', $customerId)
            ->first();

        if ($customer) {
            return $customer;
        }

        $customerAddress = Arr::get($shopifyCustomer, 'defaultAddress') ?? $fallbackAddress;

        $customerData = [
            'contact_name' => trim(
                Arr::get($shopifyCustomer, 'firstName', '').' '.
                Arr::get($shopifyCustomer, 'lastName', '')
            ) ?: Arr::get($shopifyCustomer, 'email'),
            'company_name' => Arr::get($customerAddress, 'company') ?: Arr::get($shopifyCustomer, 'email'),
            'email' => Arr::get($shopifyCustomer, 'email'),
            'phone' => Arr::get($shopifyCustomer, 'phone'),
            'external_id' => $customerId,
        ];

        if ($customerAddress) {
            data_set($customerData, 'contact_address', $this->getFormattedAddress($customerAddress));
        }

        return StoreCustomer::make()->action($shop, $customerData);
    }

    /**
     * Shopify uses countryCodeV2 (ISO2 format like "US", "GB")
     *
     * @param array $address
     * @return array
     */
    public function getFormattedAddress(array $address): array
    {
        $countryCode = Arr::get($address, 'countryCodeV2');
        $country = Country::where('code', $countryCode)->first();

        return [
            'address_line_1' => Arr::get($address, 'address1', ''),
            'address_line_2' => Arr::get($address, 'address2'),
            'sorting_code' => null,
            'postal_code' => Arr::get($address, 'zip'),
            'dependent_locality' => null,
            'locality' => Arr::get($address, 'city'),
            'administrative_area' => Arr::get($address, 'provinceCode') ?: Arr::get($address, 'province'),
            'country_code' => $country?->code,
            'country_id' => $country?->id,
        ];
    }
}
