<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:35:06 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Country\UI;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAddressData
{
    use AsObject;

    public function handle(?Shop $shop = null): array
    {
        $selectOptions = [];
        if ($shop) {
            $countries = Country::whereNotIn('id', $shop->forbidden_dispatch_countries ?? [])->get();
        } else {
            $countries = Country::all();
        }
        /** @var Country $country */
        foreach ($countries as $country) {
            $fields = Arr::get($country->data, 'fields', []);
            if (isset($fields['address_line_3'])) {
                unset($fields['address_line_3']);
            }

            foreach ($fields as $key => $field) {
                $label = Arr::get($field, 'label');
                if ($label) {
                    $translatedLabel = match ($label) {
                        'post town' => __('Town/City'),
                        'city' => __('City'),
                        'postal code' => __('Postal code'),
                        'address' => __('Address line 1'),
                        'province' => __('Province'),
                        'address line 2' => __('Address line 2'),
                        default => ucfirst($label),
                    };

                    $fields[$key]['label'] = $translatedLabel;
                }
            }


            $selectOptions[$country->id] =
                [
                    'label'               => $country->name.' ('.$country->code.')',
                    'fields'              => $fields,
                    'administrativeAreas' => Arr::get($country->data, 'administrative_areas'),
                ];
        }

        return $selectOptions;
    }
}
