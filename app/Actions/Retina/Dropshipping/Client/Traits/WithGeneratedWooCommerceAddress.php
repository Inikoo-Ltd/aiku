<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\Traits;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

trait WithGeneratedWooCommerceAddress
{
    public function getAttributes(array $customer, array $address = []): array
    {
        $country = Country::where('code', Arr::get($address, 'country'))->first();

        if (!blank($address)) {
            $address = [
                'address' => [
                    'address_line_1'      => Arr::get($address, 'address_1'),
                    'address_line_2'      => Arr::get($address, 'address_2'),
                    'sorting_code'        => null,
                    'postal_code'         => Arr::get($address, 'postcode'),
                    'dependent_locality'  => null,
                    'locality'            => Arr::get($address, 'city'),
                    'administrative_area' => Arr::get($address, 'province'),
                    'country_code'        => Arr::get($address, 'country'),
                    'country_id'          => $country->id
                ]
            ];
        }

        return [
            'contact_name' => Arr::get($customer, 'first_name') . ' ' . Arr::get($customer, 'last_name'),
            'email' => Arr::get($customer, 'email'),
            'phone' => Arr::get($customer, 'phone'),
            ...$address
        ];
    }
}
