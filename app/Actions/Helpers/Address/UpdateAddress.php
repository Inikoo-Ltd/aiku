<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 27 Sep 2021 18:41:06 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Helpers\Address;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithAddressAuditing;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;

class UpdateAddress
{
    use WithActionUpdate;
    use WithAddressAuditing;

    public function handle(Address $address, array $modelData, ?Customer $parent = null, $addressLabel = ''): Address
    {
        $oldAddressFields = [];
        if ($parent) {
            $oldAddressFields = Arr::except($address->getFields(), 'country_id');
        }

        $country = Country::find(Arr::get($modelData, 'country_id'));
        if ($country) {
            data_set($modelData, 'country_code', $country->code);
        }
        $checksum = $address->getChecksum();
        data_set($modelData, 'checksum', $checksum);

        if (Arr::exists($modelData, 'additional_name')) {
            Arr::forget($modelData, 'additional_name');
        }


        $address = $this->update($address, $modelData);
        if ($parent) {
            $this->auditAddressChange($parent, $address, $oldAddressFields, $addressLabel, false);
        }

        return $address;
    }
}
