<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Created: Thu, 20 Aug 2026 13:05:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateVirtualPallet extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

    private FulfilmentCustomer $fulfilmentCustomer;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, ActionRequest $request): Response
    {
        $indexParameters = Arr::except($request->route()->originalParameters(), ['warehouse']);

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($indexParameters),
                'title'    => __('New virtual pallet'),
                'pageHead' => [
                    'title'   => __('New virtual pallet'),
                    'model'   => $warehouse->name,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-ghost'],
                        'title' => __('Virtual pallet')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.index',
                                'parameters' => array_values($indexParameters)
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Location'),
                            'fields' => [
                                'location_id' => [
                                    'type'       => 'select_infinite',
                                    'label'      => __('Location'),
                                    'required'   => true,
                                    'options'    => [],
                                    'fetchRoute' => [
                                        'name'       => 'grp.org.warehouses.show.fulfilment.locations.index',
                                        'parameters' => [
                                            'organisation' => $this->organisation->slug,
                                            'warehouse'    => $warehouse->slug
                                        ]
                                    ],
                                    'valueProp' => 'id',
                                    'labelProp' => 'code',
                                ],
                                'customer_reference' => [
                                    'type'     => 'input',
                                    'required' => false,
                                    'label'    => __("Pallet reference (customer's)")
                                ],
                                'notes' => [
                                    'type'     => 'textarea',
                                    'required' => false,
                                    'label'    => __('Notes')
                                ],
                            ]
                        ]
                    ],
                    'route' => [
                        'name'       => 'grp.models.fulfilment-customer.virtual-pallet.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $fulfilmentCustomer->id
                        ]
                    ]
                ]
            ]
        );
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $warehouse, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexPalletsInCustomer::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating virtual pallet'),
                    ]
                ]
            ]
        );
    }
}
