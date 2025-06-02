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
    public function getAttributes(array $data): array
    {
        $country = Country::where('code', Arr::get($data, 'country'))->first();

        if (!blank($data)) {
            $address = [
                'address' => [
                    'address_line_1'      => Arr::get($data, 'address_1'),
                    'address_line_2'      => Arr::get($data, 'address_2'),
                    'sorting_code'        => null,
                    'postal_code'         => Arr::get($data, 'postcode'),
                    'dependent_locality'  => null,
                    'locality'            => Arr::get($data, 'city'),
                    'administrative_area' => Arr::get($data, 'state'),
                    'country_code'        => Arr::get($data, 'country'),
                    'country_id'          => $country->id
                ]
            ];
        }

        return [
            'contact_name' => Arr::get($data, 'first_name') . ' ' . Arr::get($data, 'last_name'),
            'email' => Arr::get($data, 'email') ?? '',
            'phone' => Arr::get($data, 'phone'),
            ...$address
        ];
    }
}
