<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:35:06 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Country\UI;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAddressData
{
    use AsObject;

    public function handle(): array
    {

        $selectOptions = [];
        /** @var Country $country */
        foreach (Country::all() as $country) {
            $fields = Arr::get($country->data, 'fields', []);
            unset($fields['address_line_3']); // TODO: remove this if the column already exist in addresses table
            $selectOptions[$country->id] =
                [
                    'label'               => $country->name . ' (' . $country->code . ')',
                    'fields'              => $fields,
                    'administrativeAreas' => Arr::get($country->data, 'administrative_areas'),
                ];
        }

        return $selectOptions;
    }
}
