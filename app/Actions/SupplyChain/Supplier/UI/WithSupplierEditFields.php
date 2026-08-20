<?php

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;

trait WithSupplierEditFields
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function supplierEditSections(Supplier $supplier): array
    {
        return [
            [
                'title'  => __('ID/contact details '),
                'icon'   => 'fal fa-address-book',
                'fields' => [
                    'image'        => [
                        'type'  => 'avatar',
                        'label' => __('Photo'),
                        'value' => $supplier->imageSources(320, 320),
                    ],
                    'code'         => [
                        'type'     => 'input',
                        'label'    => __('Code'),
                        'value'    => $supplier->code,
                        'required' => true,
                    ],
                    'company_name' => [
                        'type'  => 'input',
                        'label' => __('Company'),
                        'value' => $supplier->company_name
                    ],
                    'contact_name' => [
                        'type'  => 'input',
                        'label' => __('Contact name'),
                        'value' => $supplier->contact_name
                    ],
                    'contact_website' => [
                        'type'  => 'input',
                        'label' => __('Contact website'),
                        'value' => $supplier->contact_website
                    ],
                    'email'        => [
                        'type'    => 'input',
                        'label'   => __('Email'),
                        'value'   => $supplier->email,
                        'options' => [
                            'inputType' => 'email'
                        ]
                    ],
                    'phone'        => [
                        'type'  => 'phone',
                        'label' => __('phone'),
                        'value' => $supplier->phone,
                    ],
                    'address'      => [
                        'type'    => 'address',
                        'label'   => __('Address'),
                        'value'   => AddressResource::make($supplier->getAddress('contact'))->getArray(),
                        'options' => [
                            'countriesAddressData' => GetAddressData::run()
                        ]
                    ],
                ]
            ],
            [
                'title'  => __('settings '),
                'icon'   => 'fa-light fa-cog',
                'fields' => [
                    'currency_id' => [
                        'type'        => 'select',
                        'label'       => __('Currency'),
                        'placeholder' => __('Select a currency'),
                        'options'     => GetCurrenciesOptions::run(),
                        'value'       => $supplier->currency_id,
                        'searchable'  => true,
                        'required'    => true,
                        'mode'        => 'single'
                    ],
                    'default_product_country_origin' => [
                        'type'        => 'select',
                        'label'       => __("Products' country of origin"),
                        'placeholder' => __('Select a country'),
                        'value'       => Arr::get($supplier->settings, 'default_product_country_origin'),
                        'options'     => GetCountriesOptions::run(),
                        'mode'        => 'single'
                    ],
                    'delivery_type' => [
                        'type'        => 'select',
                        'label'       => __('Delivery type'),
                        'placeholder' => __('Select a delivery type'),
                        'options'     => [
                            ['value' => 'parcel', 'label' => __('Parcels')],
                            ['value' => 'container', 'label' => __('Container')],
                        ],
                        'value'       => Arr::get($supplier->data, 'delivery_type'),
                        'mode'        => 'single'
                    ],
                    'delivery_time' => [
                        'type'    => 'input',
                        'label'   => __('Delivery time (days)'),
                        'value'   => Arr::get($supplier->data, 'delivery_time'),
                        'options' => ['inputType' => 'number']
                    ],
                    'production_waiting_time' => [
                        'type'    => 'input',
                        'label'   => __('Production time (days)'),
                        'value'   => Arr::get($supplier->data, 'production_waiting_time'),
                        'options' => ['inputType' => 'number']
                    ],
                    'payment_terms' => [
                        'type'  => 'input',
                        'label' => __('Payment terms'),
                        'value' => Arr::get($supplier->settings, 'payment_terms'),
                    ],
                    'minimum_order' => [
                        'type'    => 'input',
                        'label'   => __('Minimum order'),
                        'value'   => Arr::get($supplier->settings, 'minimum_order'),
                        'options' => ['inputType' => 'number']
                    ],
                    'cooling_period' => [
                        'type'    => 'input',
                        'label'   => __('Cooling period between orders (days)'),
                        'value'   => Arr::get($supplier->settings, 'cooling_period'),
                        'options' => ['inputType' => 'number']
                    ],
                    'order_number_prefix' => [
                        'type'  => 'input',
                        'label' => __('Order number prefix'),
                        'value' => Arr::get($supplier->settings, 'order_number_prefix'),
                    ],
                ]
            ]
        ];
    }
}
