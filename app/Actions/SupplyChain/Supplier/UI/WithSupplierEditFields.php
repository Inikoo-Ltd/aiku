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
                        'mode'        => 'single',
                        'saveConfirmation' => $this->supplierCurrencyChangeConfirmation($supplier),
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
                'label'  => __('Pre-orders'),
                'title'  => __('Pre-orders'),
                'icon'   => 'fal fa-hourglass-half',
                'fields' => [
                    'pre_order_lead_time' => [
                        'type'        => 'input',
                        'label'       => __('Pre-order lead time'),
                        'information' => __('From the customer ordering to us dispatching, shown on the website as an estimated dispatch range. Products can set their own.'),
                        'value'       => Arr::get($supplier->settings, 'pre_order_lead_time'),
                        'options'     => ['inputType' => 'number']
                    ],
                    'pre_order_lead_time_unit' => [
                        'type'    => 'select',
                        'label'   => __('Lead time in'),
                        'options' => [
                            ['value' => 'days', 'label' => __('Days')],
                            ['value' => 'weeks', 'label' => __('Weeks')],
                        ],
                        'value'   => Arr::get($supplier->settings, 'pre_order_lead_time_unit', 'days'),
                        'mode'    => 'single'
                    ],
                    'pre_order_order_by_date' => [
                        'type'        => 'date',
                        'label'       => __('Order by date'),
                        'information' => __('The date the buying team must place the supplier order for the open pre-orders. If the minimum order is not reached by then, decide whether to order anyway or cancel the pre-orders with a full refund.'),
                        'value'       => Arr::get($supplier->settings, 'pre_order_order_by_date'),
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

    protected function supplierCurrencyChangeConfirmation(Supplier $supplier): ?array
    {
        $numberProducts = $supplier->supplierProducts()->count();
        if ($numberProducts === 0) {
            return null;
        }

        $currencyCode = $supplier->currency->code;

        return [
            'title'       => __('This supplier has :count products', ['count' => $numberProducts]),
            'description' => __('Their costs are saved in :currency. What should happen to them in the new currency?', ['currency' => $currencyCode]),
            'yesLabel'    => __('Change currency'),
            'choiceField' => 'products_currency',
            'choices'     => [
                [
                    'value'       => 'relabel',
                    'label'       => __('Keep the numbers, they were already in the new currency'),
                    'description' => __('Use this when :currency was a mistake: a cost of 100 stays 100, only the currency changes.', ['currency' => $currencyCode]),
                ],
                [
                    'value'       => 'convert',
                    'label'       => __("Convert the costs at today's exchange rate"),
                    'description' => __('Use this when the supplier really changed currency: every cost is recalculated from :currency.', ['currency' => $currencyCode]),
                ],
            ],
        ];
    }
}
