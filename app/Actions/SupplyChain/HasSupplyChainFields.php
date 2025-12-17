<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 19:12:12 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Helpers\Address;

trait HasSupplyChainFields
{
    public function supplyChainFields(): array
    {
        return [
            [
                'title'  => __('ID/contact details '),
                'icon'   => 'fal fa-address-book',
                'fields' => [
                    'code' => [
                        'type'    => 'input',
                        'label'   => __('Code'),
                        'value'   => '',
                        'required' => true
                    ],
                    'name' => [
                        'type'    => 'input',
                        'label'   => __('Name'),
                        'value'   => '',
                        'required' => true
                    ],

                    'contact_name' => [
                        'type'    => 'input',
                        'label'   => __('Contact Name'),
                        'value'   => '',
                        'required' => true
                    ],

                    'contact_website' => [
                        'type'    => 'input',
                        'label'   => __('Contact Website'),
                        'value'   => '',
                        'required' => false
                    ],

                    'email' => [
                        'type'    => 'input',
                        'label'   => __('Email'),
                        'value'   => '',
                        'options' => [
                            'inputType' => 'email'
                        ]
                    ],
                    'phone' => [
                        'type'  => 'phone',
                        'label' => __('Phone'),
                        'value' => ''
                    ],
                    'address' => [
                        'type'  => 'address',
                        'label' => __('Address'),
                        'value' => AddressFormFieldsResource::make(
                            new Address(
                                [
                                    'country_id' => app('group')->country_id,
                                ]
                            )
                        )->getArray(),
                        'options' => [
                            'countriesAddressData' => GetAddressData::run()

                        ]
                    ],

                ]
            ],
            [
                'title'  => __('Settings'),
                'icon'   => 'fa-light fa-cog',
                'fields' => [
                    'currency_id' => [
                        'type'        => 'select',
                        'label'       => __('Currency'),
                        'placeholder' => __('Select a currency'),
                        'options'     => GetCurrenciesOptions::run(),
                        'required'    => true,
                        'mode'        => 'single',
                        'searchable'  => true
                    ],

                    'default_product_country_origin' => [
                        'type'        => 'select',
                        'label'       => __("Asset's country of origin"),
                        'placeholder' => __('Select a country'),
                        'options'     => GetCountriesOptions::run(),
                        'mode'        => 'single',
                        'searchable'  => true
                    ],
                ]
            ]
        ];
    }
}
