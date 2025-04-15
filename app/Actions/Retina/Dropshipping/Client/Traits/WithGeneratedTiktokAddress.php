<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\Traits;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

trait WithGeneratedTiktokAddress
{
    public function getAddressAttributes(array $address = []): array
    {
        $customer = $address;
        $country = Country::where('code', Arr::get($address, 'region_code'))->first();

        if (!blank($address)) {
            $address = [
                'address' => [
                    'address_line_1'      => Arr::get($address, 'address_line1'),
                    'address_line_2'      => Arr::get($address, 'address_line2'),
                    'sorting_code'        => null,
                    'postal_code'         => Arr::get($address, 'postal_code'),
                    'dependent_locality'  => null,
                    'locality'            => Arr::get($address, 'district_info.2.address_name'),
                    'administrative_area' => Arr::get($address, 'district_info.1.address_name'),
                    'country_code'        => Arr::get($address, 'region_code'),
                    'country_id'          => $country->id
                ]
            ];
        }

        return [
            'contact_name' => $customer['first_name'] . ' ' . Arr::get($customer, 'last_name'),
            'email' => $customer['email'],
            'phone' => $customer['phone_number'],
            ...$address
        ];
    }
}
