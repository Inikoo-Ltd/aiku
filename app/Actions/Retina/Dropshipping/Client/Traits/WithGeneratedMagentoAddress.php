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

trait WithGeneratedMagentoAddress
{
    public function getAttributes(array $data): array
    {
        $address = [];
        $contactAddress = $data;
        $country = Country::where('code', Arr::get($contactAddress, 'country_id'))->first();

        if (!blank($contactAddress)) {
            $address = [
                'address' => [
                    'address_line_1' => Arr::get($contactAddress, 'street.0', ''),
                    'address_line_2' => Arr::get($contactAddress, 'street.1', ''),
                    'sorting_code' => null,
                    'postal_code' => Arr::get($contactAddress, 'postcode'),
                    'dependent_locality' => null,
                    'locality' => Arr::get($contactAddress, 'city'),
                    'administrative_area' => Arr::get($contactAddress, 'region'),
                    'country_code' => Arr::get($contactAddress, 'country_id'),
                    'country_id' => $country?->id
                ]
            ];
        }

        return [
            'contact_name' => Arr::get($contactAddress, 'firstname') . ' ' . Arr::get($contactAddress, 'lastname'),
            'email' => Arr::get($contactAddress, 'email', ''),
            'phone' => Arr::get($contactAddress, 'telephone'),
            ...$address
        ];
    }
}
