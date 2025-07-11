<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\Traits;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

trait WithGeneratedShopifyAddress
{
    public function getShopifyAttributesFromWebhook(array $customerClient, array $address = []): array
    {
        $country = Country::where('code', Arr::get($address, 'country_code'))->first();

        if (!blank($address)) {
            $address = [
                'address' => [
                    'address_line_1'      => Arr::get($address, 'address1'),
                    'address_line_2'      => Arr::get($address, 'address2'),
                    'sorting_code'        => null,
                    'postal_code'         => Arr::get($address, 'zip'),
                    'dependent_locality'  => null,
                    'locality'            => Arr::get($address, 'city'),
                    'administrative_area' => Arr::get($address, 'province'),
                    'country_code'        => Arr::get($address, 'country_code'),
                    'country_id'          => $country->id
                ]
            ];
        }

        return [
            'contact_name' => $customerClient['first_name'] . ' ' . Arr::get($customerClient, 'last_name'),
            'email' => $customerClient['email'],
            'phone' => $customerClient['phone'],
            ...$address
        ];
    }
}
