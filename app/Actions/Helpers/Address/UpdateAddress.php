<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 27 Sep 2021 18:41:06 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Helpers\Address;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

class UpdateAddress
{
    use WithActionUpdate;

    public function handle(Address $address, array $modelData): Address
    {
        $country = Country::find(Arr::get($modelData, 'country_id'));
        if ($country) {
            data_set($modelData, 'country_code', $country->code);
        }
        $checksum = $address->getChecksum();
        data_set($modelData, 'checksum', $checksum);

        return $this->update($address, $modelData);
    }
}
