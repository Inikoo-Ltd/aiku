<?php

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
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
                        'value'       => $agent->organisation->currency_id,
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
                    'buyers_manage_stock_deliveries' => [
                        'type'        => 'toggle',
                        'label'       => __('Buyers can mark containers as dispatched'),
                        'information' => __('Off: only the agent marks its containers as dispatched. On: buyers can too, once the invoice is made or entered'),
                        'value'       => (bool) Arr::get($agent->settings, 'buyers_manage_stock_deliveries', false),
                    ],
                    'receives_goods_in_own_warehouse' => [
                        'type'        => 'toggle',
                        'label'       => __('Receives goods in its own warehouse'),
                        'information' => __('On: each supplier delivery is recorded as a goods receipt at the agent\'s warehouse, and only goods received there can go in a container, LCL, air freight or parcel'),
                        'value'       => $agent->receivesGoodsInOwnWarehouse(),
                    ],
                ]
            ],
            [
                'title'  => __('PO journey'),
                'icon'   => 'fal fa-route',
                'fields' => $this->poJourneyEditFields($agent),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function poJourneyEditFields(Agent $agent): array
    {
        $journey = $agent->poJourney(true);
        $labels  = PurchaseOrderJourneyStageEnum::labels();
        $texts   = PurchaseOrderJourneyStageEnum::descriptions();

        $fields = [
            'po_journey_cho_mode' => [
                'type'        => 'select',
                'label'       => __('Clean Hand Over is done when'),
                'information' => __('Next container: every item of the order is in a container, or the agent marks it. For agents that send goods on their next container. Shipping agent: the agent marks the goods as handed to the shipping agent (forwarder). A dispatched container completes it in both. Who may mark it does not change'),
                'options'     => [
                    ['value' => 'next_container', 'label' => __('The goods are ready for the next container')],
                    ['value' => 'forwarder', 'label' => __('The goods are sent to the shipping agent')],
                ],
                'value'       => $journey['cho_mode'],
                'required'    => true,
                'mode'        => 'single',
            ],
            'po_journey_ready_checks' => [
                'type'        => 'select',
                'label'       => __('Final checks shown on :stage', ['stage' => $labels['ready']]),
                'information' => __('The checks listed on the order while it waits for its quality check. They only show what is done; the stage is done when the quality check is approved'),
                'options'     => collect(PurchaseOrderStageEnum::readyCheckLabels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'value'       => $journey['ready_checks'],
                'mode'        => 'multiple',
            ],
        ];

        foreach (PurchaseOrderJourneyStageEnum::cases() as $stage) {
            $key = $stage->value;

            $fields['po_journey_label_'.$key] = [
                'type'        => 'input',
                'label'       => __('Name of :stage', ['stage' => $labels[$key]]),
                'information' => __('Leave empty to use the standard name'),
                'value'       => $journey['stages'][$key]['label'],
            ];
            $fields['po_journey_description_'.$key] = [
                'type'        => 'input',
                'label'       => __('Description of :stage', ['stage' => $labels[$key]]),
                'information' => __('Leave empty to use the standard description: :description', ['description' => $texts[$key]]),
                'value'       => $journey['stages'][$key]['description'],
            ];
            $fields['po_journey_days_'.$key] = [
                'type'        => 'input',
                'label'       => __('Days for :stage', ['stage' => $labels[$key]]),
                'information' => __('Days after the previous stage. Leave empty to fit the delivery time; default :days', ['days' => $stage->defaultDays('agent')]),
                'value'       => $journey['stages'][$key]['days'],
                'options'     => ['inputType' => 'number'],
            ];
        }

        return $fields;
    }
}
