<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jan 2024 20:06:16 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Fields;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Address;

trait StoreCustomerFields
{
    private function getBlueprint(Shop $shop): array
    {
        return [
            [
                'title'  => __('Contact'),
                'fields' => [
                    'company_name' => [
                        'type'  => 'input',
                        'label' => __('Company'),
                        'value' => ''
                    ],
                    'contact_name' => [
                        'type'  => 'input',
                        'label' => __('Contact name'),
                        'value' => ''
                    ],
                    'email' => [
                        'type'  => 'input',
                        'label' => __('Email'),
                        'value' => ''
                    ],
                    'phone' => [
                        'type'  => 'input',
                        'label' => __('phone'),
                        'value' => ''
                    ],
                    'interest' => [
                        'type'    => 'interest',
                        'options' => [
                            [
                                'value' => 'pallets_storage',
                                'label' => __('Pallets Storage')
                            ],
                            [
                                'value' => 'items_storage',
                                'label' => __('Items Storage')
                            ],
                            [
                                'value' => 'dropshipping',
                                'label' => __('Dropshipping')
                            ],
                        ],
                        'label' => __('User interest'),
                        'value' => ['pallets_storage']
                    ],
                    'contact_address'      => [
                        'type'    => 'address',
                        'label'   => __('Address'),
                        'value'   => AddressFormFieldsResource::make(
                            new Address(
                                [
                                    'country_id' => $shop->country_id,

                                ]
                            )
                        )->getArray(),
                        'options' => [
                            'countriesAddressData' => GetAddressData::run()

                        ]
                    ]
                ]
            ]
        ];
    }

}
