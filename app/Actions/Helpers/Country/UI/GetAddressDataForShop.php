<?php

/*
 * Author Louis Perez
 * Created on 01-07-2026-08h-43m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Country\UI;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAddressDataForShop
{
    use AsObject;

    public function handle(?Shop $shop = null, bool $excludeForbiddenBilling = false, bool $excludeForbiddenDelivery = false): array
    {
        $selectOptions = [];

        $countries = Country::where('status', true)
            ->where('show_in_address', true);

        $forbiddenCountries = [];

        if ($excludeForbiddenBilling) {
            $forbiddenCountries = array_merge($forbiddenCountries, $shop->bannedBillingCountries());
        }

        if ($excludeForbiddenDelivery) {
            $forbiddenCountries = array_merge($forbiddenCountries, $shop->bannedDeliveryCountries());
        }

        // Only ban empty/null postcode (Since it means ban whole country). Will still show the other, validation is on BE
        $forbiddenCountries = array_filter($forbiddenCountries, fn ($item) => empty($item['postcode']));

        $countries->whereNotIn('code', array_keys($forbiddenCountries));

        $countries = $countries->get();

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

            if (Arr::exists($fields, 'additional_name')) {
                Arr::forget($fields, 'additional_name');
            }


            $selectOptions[$country->id] =
                [
                    'label'               => $country->name.' ('.$country->code.')',
                    'code'                => $country->code,
                    'fields'              => $fields,
                    'administrativeAreas' => Arr::get($country->data, 'administrative_areas'),
                ];
        }

        return $selectOptions;
    }
}
