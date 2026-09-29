<?php

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
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
                'label'  => __('Contact details'),
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
                        ]
                    ],
                ]
            ],
            [
                'label'  => __('Settings'),
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
                ]
            ],
            [
                'label'  => __('Purchase orders'),
                'title'  => __('Purchase orders'),
                'icon'   => 'fal fa-envelope',
                'fields' => [
                    'po_by_email' => [
                        'type'        => 'toggle',
                        'label'       => __('Send purchase orders by email'),
                        'information' => __('Purchase orders get an "Email to supplier" button that prepares the email with the PDF'),
                        'value'       => (bool)Arr::get($supplier->settings, 'po_by_email', false),
                    ],
                    'po_email' => [
                        'type'        => 'input',
                        'label'       => __('Email for purchase orders'),
                        'placeholder' => $supplier->email ?? '',
                        'information' => __('Leave empty to use the supplier email'),
                        'value'       => Arr::get($supplier->settings, 'po_email'),
                    ],
                ]
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function supplierAgentSection(Supplier $supplier): array
    {
        return [
            'label'  => __('Agent'),
            'title'  => __('Agent'),
            'icon'   => 'fal fa-exchange',
            'fields' => [
                'agent_id' => [
                    'type'        => 'select',
                    'label'       => __('Agent'),
                    'placeholder' => __('Select an agent'),
                    'information' => __('Organisations working with the agent buy this supplier through it; the others stop buying it directly'),
                    'options'     => Agent::where('group_id', $supplier->group_id)
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Agent $agent) => ['value' => $agent->id, 'label' => $agent->name.' ('.$agent->code.')'])
                        ->all(),
                    'value'       => $supplier->agent_id,
                    'searchable'  => true,
                    'mode'        => 'single'
                ],
                ...($supplier->agent_id ? [
                    'make_independent' => [
                        'type'        => 'action',
                        'label'       => __('Independent supplier'),
                        'information' => __('Takes the supplier away from its agent; organisations buy it directly again'),
                        'action'      => [
                            'type'  => 'button',
                            'style' => 'secondary',
                            'label' => __('Remove from agent'),
                            'route' => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.supplier.update',
                                'parameters' => $supplier->id,
                                'body'       => ['agent_id' => null],
                            ],
                        ],
                    ],
                ] : []),
            ]
        ];
    }
}
