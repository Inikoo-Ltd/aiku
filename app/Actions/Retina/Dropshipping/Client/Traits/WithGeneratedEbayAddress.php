<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Client\Traits;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

trait WithGeneratedEbayAddress
{
    public function getAttributes(array $data): array
    {
        $address = [];
        $contactAddress = Arr::get($data, 'contactAddress');
        $country = Country::where('code', Arr::get($contactAddress, 'countryCode'))->first();

        if (!blank($contactAddress)) {
            $address = [
                'address' => [
                    'address_line_1'      => Arr::get($contactAddress, 'addressLine1', ''),
                    'address_line_2'      => Arr::get($contactAddress, 'addressLine2', ''),
                    'sorting_code'        => null,
                    'postal_code'         => Arr::get($contactAddress, 'postalCode'),
                    'dependent_locality'  => null,
                    'locality'            => Arr::get($contactAddress, 'city'),
                    'administrative_area' => Arr::get($contactAddress, 'stateOrProvince'),
                    'country_code'        => Arr::get($contactAddress, 'countryCode'),
                    'country_id'          => $country->id
                ]
            ];
        }

        return [
            'contact_name' => Arr::get($data, 'fullName'),
            'email' => Arr::get($data, 'email') ?? '',
            'phone' => Arr::get($data, 'primaryPhone.phoneNumber'),
            ...$address
        ];
    }
}
