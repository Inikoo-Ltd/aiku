<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\Marketplace\Supplier\UI;

use App\Actions\Assets\Country\UI\GetAddressData;
use App\Actions\Assets\Country\UI\GetCountriesOptions;
use App\Actions\Assets\Currency\UI\GetCurrenciesOptions;
use App\Actions\InertiaAction;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\Agent;
use App\Models\Procurement\Supplier;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMarketplaceSupplier extends InertiaAction
{
    public function handle(Supplier $supplier): Supplier
    {
        return $supplier;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo('procurement.edit');

        return $request->user()->hasPermissionTo("procurement.view");
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisation($request);
        return $this->handle($supplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMarketplaceAgent(Agent $agent, Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisation($request);
        return $this->handle($supplier);
    }

    public function htmlResponse(Supplier $supplier, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('edit marketplace supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'navigation'                              => [
                    'previous' => $this->getPrevious($supplier, $request),
                    'next'     => $this->getNext($supplier, $request),
                ],
                'pageHead'    => [
                    'title'     => $supplier->code,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $this->routeName),
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ]
                    ]
                ],

                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('ID/contact details'),
                            'icon'   => 'fal fa-address-book',
                            'fields' => [

                                'code'         => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => $supplier->code,
                                    'required' => true,
                                ],
                                'company_name' => [
                                    'type'  => 'input',
                                    'label' => __('company'),
                                    'value' => $supplier->company_name
                                ],
                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $supplier->contact_name
                                ],
                                'email'        => [
                                    'type'    => 'input',
                                    'label'   => __('email'),
                                    'value'   => $supplier->email,
                                    'options' => [
                                        'inputType' => 'email'
                                    ]
                                ],
                                'phone'        => [
                                    'type'    => 'phone',
                                    'label'   => __('phone'),
                                    'value'   => $supplier->phone,
                                ],
                                'address'      => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressResource::make($supplier->getAddress())->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()

                                    ]
                                ],

                            ]
                        ],

                        /*
                        [
                            'title'  => __("supplier's products settings"),
                            'fields' => [

                                'allow on demand'              => [
                                    'type'  => 'input',
                                    'label' => __('allow on demand'),
                                    'value' => ''
                                ],
                                'products origin country code' => [
                                    'type'  => 'input',
                                    'label' => __('products origin country code'),
                                    'value' => ''
                                ],
                            ]
                        ],
                        [
                            'title'  => __('waiting times'),
                            'fields' => [

                                'delivery time' => [
                                    'type'  => 'input',
                                    'label' => __('delivery time (days)'),
                                    'value' => ''
                                ],
                            ]
                        ],
                        [
                            'title'  => __('payment'),
                            'fields' => [

                                'incoterm'      => [
                                    'type'  => 'input',
                                    'label' => __('incoterm'),
                                    'value' => ''
                                ],
                                'currency'      => [
                                    'type'  => 'input',
                                    'label' => __('currency'),
                                    'value' => ''
                                ],
                                'payment terms' => [
                                    'type'  => 'input',
                                    'label' => __('payment terms'),
                                    'value' => ''
                                ],
                            ]
                        ],
                        [
                            'title'  => __('terms and conditions'),
                            'fields' => [

                                't&c'                 => [
                                    'type'  => 'input',
                                    'label' => __('t&c'),
                                    'value' => ''
                                ],
                                'include general t&c' => [
                                    'type'  => 'input',
                                    'label' => __('include general t&c'),
                                    'value' => ''
                                ],
                            ]
                        ],
                        [
                            'title'  => __('purchase order settings'),
                            'fields' => [
                                'minimum order'                 => [
                                    'type'  => 'input',
                                    'label' => __('minimum order (EUR)'),
                                    'value' => ''
                                ],
                                'cooling period between orders' => [
                                    'type'  => 'input',
                                    'label' => __('cooling period between orders (days)'),
                                    'value' => ''
                                ],

                                'order number format'           => [
                                    'type'  => 'input',
                                    'label' => __('order number format'),
                                    'value' => ''
                                ],
                                'last incremental order number' => [
                                    'type'  => 'input',
                                    'label' => __('last incremental order number'),
                                    'value' => ''
                                ],
                            ]

                        ],


                        [
                            'title'  => __('currency'),
                            'fields' => [

                                'currency_id' => [
                                    'type'  => 'currencies',
                                    'label' => __('currency'),
                                    'value' => ''
                                ],

                            ]
                        ],
                        */

                        [
                            'title'  => __('settings'),
                            'icon'   => 'fa-light fa-cog',
                            'fields' => [
                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('currency'),
                                    'placeholder' => __('Select a currency'),
                                    'options'     => GetCurrenciesOptions::run(),
                                    'value'       => $supplier->currency_id,
                                    'searchable'  => true,
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],

                                'default_product_country_origin' => [
                                    'type'        => 'select',
                                    'label'       => __("Product's country of origin"),
                                    'placeholder' => __('Select a country'),
                                    'value'       => $supplier->code,
                                    'options'     => GetCountriesOptions::run(),
                                    'mode'        => 'single'
                                ],
                            ]
                        ]


                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'models.supplier.update',
                            'parameters'=> $supplier->slug

                        ],
                    ]
                ],
            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowMarketplaceSupplier::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('editing').')'
        );
    }

    public function getPrevious(Supplier $supplier, ActionRequest $request): ?array
    {
        $previous = Supplier::where('code', '<', $supplier->code)->when(true, function ($query) use ($supplier, $request) {
            if ($request->route()->getName() == 'grp.procurement.marketplace.agents.show.suppliers.show') {
                $query->where('suppliers.agent_id', $supplier->agent_id);
            }
        })->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Supplier $supplier, ActionRequest $request): ?array
    {
        $next = Supplier::where('code', '>', $supplier->code)->when(true, function ($query) use ($supplier, $request) {
            if ($request->route()->getName() == 'grp.procurement.marketplace.agents.show.suppliers.edit') {
                $query->where('suppliers.agent_id', $supplier->agent_id);
            }
        })->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Supplier $supplier, string $routeName): ?array
    {
        if(!$supplier) {
            return null;
        }

        return match ($routeName) {
            'grp.procurement.marketplace.suppliers.edit'=> [
                'label'=> $supplier->name,
                'route'=> [
                    'name'      => $routeName,
                    'parameters'=> [
                        'supplier'  => $supplier->slug
                    ]

                ]
            ],
            'grp.procurement.marketplace.agents.show.suppliers.edit' => [
                'label'=> $supplier->name,
                'route'=> [
                    'name'      => $routeName,
                    'parameters'=> [
                        'agent'     => $supplier->agent->slug,
                        'supplier'  => $supplier->slug
                    ]

                ]
            ]
        };
    }
}
