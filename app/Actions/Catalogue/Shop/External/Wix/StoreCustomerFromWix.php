<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

class StoreCustomerFromWix extends OrgAction
{
    /**
     * Who receives the order first, then where the shipping method points, then the payer.
     */
    private const array DESTINATION_PATHS = [
        'recipientInfo',
        'shippingInfo.logistics.shippingDestination',
        'billingInfo',
    ];

    public function handle(Shop $shop, array $wixOrder): Customer
    {
        $email      = Arr::get($wixOrder, 'buyerInfo.email');
        $externalId = Arr::get($wixOrder, 'buyerInfo.contactId') ?: $email;
        $contact    = Arr::get($wixOrder, 'billingInfo.contactDetails') ?: $this->getWixDestination($wixOrder, 'contactDetails');

        $contactName = trim(Arr::get($contact, 'firstName', '').' '.Arr::get($contact, 'lastName', '')) ?: $email;

        $customerData = [
            'contact_name'    => $contactName,
            'company_name'    => Arr::get($contact, 'company') ?: $contactName,
            'email'           => $email,
            'external_id'     => $externalId,
            'reference'       => $externalId,
            'contact_address' => $this->getFormattedAddress($wixOrder),
        ];

        if ($phone = Arr::get($contact, 'phone')) {
            $customerData['phone'] = $phone;
        }

        $customer = Customer::where('shop_id', $shop->id)
            ->where('external_id', $externalId)
            ->first();

        if ($customer) {
            return UpdateCustomer::make()->action(customer: $customer, modelData: Arr::except($customerData, ['reference']), strict: false);
        }

        return StoreCustomer::make()->action(shop: $shop, modelData: $customerData, strict: false);
    }

    public function getFormattedAddress(array $wixOrder): array
    {
        $wixAddress = $this->getWixDestination($wixOrder, 'address');

        $country = Country::where('code', Arr::get($wixAddress, 'country'))->first()
            ?? Country::where('code', 'GB')->first();

        $street = trim(Arr::get($wixAddress, 'streetAddress.number', '').' '.Arr::get($wixAddress, 'streetAddress.name', ''));

        return [
            'address_line_1'      => Arr::get($wixAddress, 'addressLine') ?: $street,
            'address_line_2'      => Arr::get($wixAddress, 'addressLine2') ?: Arr::get($wixAddress, 'streetAddress.apt'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($wixAddress, 'postalCode'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($wixAddress, 'city'),
            'administrative_area' => Arr::get($wixAddress, 'subdivision'),
            'country_code'        => $country?->code,
            'country_id'          => $country?->id,
        ];
    }

    private function getWixDestination(array $wixOrder, string $key): array
    {
        foreach (self::DESTINATION_PATHS as $path) {
            $value = Arr::get($wixOrder, $path.'.'.$key);

            if (is_array($value) && filled($value)) {
                return $value;
            }
        }

        return [];
    }
}
