<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jan 2024 09:36:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateSupplier extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    private const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    public function handle(Group|Agent $parent, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = array_values($request->route()->originalParameters());

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $request->route()->originalParameters()
                ),
                'title'       => __('New Supplier'),
                'pageHead'    => [
                    'title'   => __('New Supplier'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $routeName),
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->getBlueprint($parent),
                    'route'     => $this->getStoreRoute($parent),
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $request);
    }

    public function inAgent(Agent $agent, ActionRequest $request): Response
    {
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent, $request);
    }

    protected function getBlueprint(Group|Agent $parent): array
    {
        $isInAgent = $parent instanceof Agent;

        return array_values(array_filter([
            $isInAgent ? null : [
                'title'  => __('Type'),
                'icon'   => 'fal fa-truck',
                'fields' => [
                    'delivery_type' => [
                        'type'        => 'select',
                        'label'       => __('Delivery Type'),
                        'value'       => null,
                        'mode'        => 'single',
                        'options'     => [
                            ['label' => __('Container'), 'value' => 'container'],
                            ['label' => __('Parcel'), 'value' => 'parcel'],
                        ],
                    ],
                ],
            ],
            $isInAgent ? null : [
                'title'  => __('Delivery Terms'),
                'icon'   => 'fal fa-ship',
                'fields' => [
                    'incoterm'       => [
                        'type'    => 'select',
                        'label'   => __('Incoterm'),
                        'value'   => null,
                        'mode'    => 'single',
                        'options' => array_map(fn ($code) => ['label' => $code, 'value' => $code], self::INCOTERMS),
                        'showIf'  => ['field' => 'delivery_type', 'value' => 'container'],
                    ],
                    'port_of_export' => [
                        'type'   => 'input',
                        'label'  => __('Port of Export'),
                        'value'  => '',
                        'showIf' => ['field' => 'delivery_type', 'value' => 'container'],
                    ],
                    'port_of_import' => [
                        'type'   => 'input',
                        'label'  => __('Port of Import'),
                        'value'  => '',
                        'showIf' => ['field' => 'delivery_type', 'value' => 'container'],
                    ],
                ],
            ],
            [
                'title'  => __('ID/Contact Details'),
                'icon'   => 'fal fa-address-book',
                'fields' => [
                    'code'            => [
                        'type'      => 'input',
                        'label'     => __('Code'),
                        'value'     => '',
                        'uppercase' => true,
                        'required'  => true,
                    ],
                    'name'            => [
                        'type'     => 'input',
                        'label'    => __('Name'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'contact_name'    => [
                        'type'     => 'input',
                        'label'    => __('Contact Name'),
                        'value'    => '',
                        'required' => true,
                    ],
                    'company_name'    => [
                        'type'     => 'input',
                        'label'    => __('Company Name'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'contact_website' => [
                        'type'     => 'input',
                        'label'    => __('Contact Website'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'email'           => [
                        'type'    => 'input',
                        'label'   => __('Email'),
                        'value'   => '',
                        'options' => [
                            'inputType' => 'email',
                        ],
                    ],
                    'phone'           => [
                        'type'  => 'phone',
                        'label' => __('Phone'),
                        'value' => '',
                    ],
                    'address'         => [
                        'type'     => 'address',
                        'label'    => __('Address'),
                        'value'    => AddressFormFieldsResource::make(new Address(['country_id' => group()->country_id]))->getArray(),
                        'required' => true,
                        'options'  => [
                            'countriesAddressData' => GetAddressData::run(),
                        ],
                    ],
                ],
            ],
            [
                'title'  => __("Supplier's Products Settings"),
                'icon'   => 'fal fa-cog',
                'fields' => [
                    'default_product_allow_on_demand' => [
                        'type'  => 'toggle',
                        'label' => __('Allow On Demand'),
                        'value' => false,
                    ],
                    'default_product_country_origin'  => [
                        'type'        => 'select',
                        'label'       => __("Product's Country of Origin"),
                        'options'     => GetCountriesOptions::run(),
                        'mode'        => 'single',
                        'searchable'  => true,
                    ],
                ],
            ],
            [
                'title'  => __("Waiting Times"),
                'icon'   => 'fal fa-clock',
                'fields' => [
                    'production_waiting_time' => [
                        'type'  => 'input',
                        'label' => __('Production Waiting Time (Days)'),
                        'value' => '',
                    ],
                    'delivery_time'           => [
                        'type'  => 'input',
                        'label' => __('Delivery Time (Days)'),
                        'value' => '',
                    ],
                ],
            ],
            [
                'title'  => __("Payment Settings"),
                'icon'   => 'fal fa-credit-card',
                'fields' => [
                    'currency_id'   => [
                        'type'       => 'select',
                        'label'      => __('Currency'),
                        'options'    => GetCurrenciesOptions::run(),
                        'required'   => true,
                        'mode'       => 'single',
                        'searchable' => true,
                    ],
                    'payment_terms' => [
                        'type'  => 'input',
                        'label' => __('Payment Terms'),
                        'value' => '',
                    ],
                ],
            ],
            $isInAgent ? null : [
                'title'  => __("Purchase Order Settings"),
                'icon'   => 'fal fa-file-invoice-dollar',
                'fields' => [
                    'minimum_order'       => [
                        'type'      => 'input',
                        'label'     => __('Minimum Order'),
                        'value'     => '',
                        'labelFrom' => [
                            'field'   => 'currency_id',
                            'options' => Currency::pluck('code', 'id'),
                        ],
                    ],
                    'cooling_period'      => [
                        'type'        => 'input',
                        'label'       => __('Cooling Period (Days)'),
                        'information' => __('Minimum days between two orders to this supplier'),
                        'value'       => '',
                    ],
                    'order_number_prefix' => [
                        'type'        => 'input',
                        'label'       => __('Order Number Prefix'),
                        'information' => __('Only the prefix, the system appends a random number to keep each order number unique'),
                        'value'       => '',
                        'uppercase'   => true,
                        'required'    => true,
                    ],
                ],
            ],
        ]));
    }

    protected function getStoreRoute(Group|Agent $parent): array
    {
        if ($parent instanceof Agent) {
            return [
                'name'       => 'grp.models.agent.supplier.store',
                'parameters' => [$parent->id],
            ];
        }

        return [
            'name' => 'grp.models.supplier.store',
        ];
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexSuppliers::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Supplier'),
                    ],
                ],
            ]
        );
    }
}
