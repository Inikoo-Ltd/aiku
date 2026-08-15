<?php

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Illuminate\Support\Arr;

trait WithAgentEditFields
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function agentEditSections(Agent $agent): array
    {
        return [
            [
                'title'  => __('ID/contact details'),
                'icon'   => 'fal fa-address-book',
                'fields' => [
                    'image'        => [
                        'type'  => 'avatar',
                        'label' => __('Photo'),
                        'value' => $agent->imageSources(320, 320),
                    ],
                    'code'         => [
                        'type'  => 'input',
                        'label' => __('Code'),
                        'value' => $agent->code
                    ],
                    'name'         => [
                        'type'  => 'input',
                        'label' => __('Name'),
                        'value' => $agent->organisation->name
                    ],
                    'contact_name' => [
                        'type'  => 'input',
                        'label' => __('Contact Name'),
                        'value' => $agent->organisation->contact_name
                    ],
                    'email'        => [
                        'type'    => 'input',
                        'label'   => __('Email'),
                        'value'   => $agent->organisation->email,
                        'options' => [
                            'inputType' => 'email'
                        ]
                    ],
                    'phone'        => [
                        'type'  => 'phone',
                        'label' => __('phone'),
                        'value' => $agent->organisation->phone,
                    ],
                    'address'      => [
                        'type'    => 'address',
                        'label'   => __('Address'),
                        'value'   => AddressResource::make($agent->organisation->address)->getArray(),
                        'options' => [
                            'countriesAddressData' => GetAddressData::run()
                        ]
                    ],
                ]
            ],
            [
                'title'  => __('settings'),
                'icon'   => 'fa-light fa-cog',
                'fields' => [
                    'currency_id' => [
                        'type'        => 'select',
                        'label'       => __('Currency'),
                        'placeholder' => __('Select a currency'),
                        'options'     => GetCurrenciesOptions::run(),
                        'value'       => $agent->currency_id,
                        'required'    => true,
                        'mode'        => 'single'
                    ],
                    'default_product_country_origin' => [
                        'type'        => 'select',
                        'label'       => __("Products' country of origin"),
                        'placeholder' => __('Select a country'),
                        'value'       => Arr::get($agent->settings, 'default_product_country_origin'),
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
                        'value'       => Arr::get($agent->data, 'delivery_type'),
                        'mode'        => 'single'
                    ],
                    'delivery_time' => [
                        'type'    => 'input',
                        'label'   => __('Delivery time (days)'),
                        'value'   => Arr::get($agent->data, 'delivery_time'),
                        'options' => ['inputType' => 'number']
                    ],
                    'payment_terms' => [
                        'type'  => 'input',
                        'label' => __('Payment terms'),
                        'value' => Arr::get($agent->settings, 'payment_terms'),
                    ],
                    'minimum_order' => [
                        'type'    => 'input',
                        'label'   => __('Minimum order'),
                        'value'   => Arr::get($agent->settings, 'minimum_order'),
                        'options' => ['inputType' => 'number']
                    ],
                    'cooling_period' => [
                        'type'    => 'input',
                        'label'   => __('Cooling period between orders (days)'),
                        'value'   => Arr::get($agent->settings, 'cooling_period'),
                        'options' => ['inputType' => 'number']
                    ],
                ]
            ]
        ];
    }
}
