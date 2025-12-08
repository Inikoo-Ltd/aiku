<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Nov 2024 10:29:15 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Helpers\Address;
use Illuminate\Support\Arr;

trait WithStoreModelAddress
{
    protected function storeModelAddress($addressData): Address
    {
        data_set($addressData, 'is_fixed', false);
        data_set($addressData, 'usage', 1);
        $addressData = Arr::only($addressData, ['group_id', 'address_line_1', 'address_line_2', 'sorting_code', 'postal_code', 'dependent_locality', 'locality', 'administrative_area', 'country_code', 'country_id', 'is_fixed',  'usage']);

        /** @var Address $address */
        $address = Address::create($addressData);

        return $address;
    }
}
