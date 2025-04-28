<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Feb 2024 12:39:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateLocation extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    private WarehouseArea|Warehouse $parent;

    public function handle(ActionRequest $request): Response
    {
        if ($this->parent instanceof WarehouseArea) {
            $routeName = preg_replace('/.locations.create$/', '', $request->route()->getName());
        } else {
            $routeName = preg_replace('/create$/', 'index', $request->route()->getName());
        }

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New location'),
                'pageHead'    => [
                    'title'   => __('New location'),
                    'icon'    => 'fal fa-warehouse',

                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $request->route()->originalParameters()
                            ]

                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('id'),
                            'fields' => [

                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => '',
                                    'required' => true
                                ],
                            ]
                        ],
                        [
                            'title'  => __('capacity'),
                            // 'icon'   => 'fa-light fa-phone',
                            'fields' => [
                                'max_weight' => [
                                    'type'  => 'input',
                                    'label' => __('max weight (kg)'),
                                    'value' => '',
                                ],
                                'max_volume' => [
                                    'type'  => 'input',
                                    'label' => __('max volume (m³)'),
                                    'value' => '',
                                ],
                            ]
                        ],


                    ],
                    'route'     =>
                        match (class_basename($this->parent::class)) {
                            'Warehouse' => [
                                'name'       => 'grp.models.warehouse.location.store',
                                'parameters' => $this->parent->id
                            ],
                            'WarehouseArea' => [
                                'name'       => 'grp.models.warehouse_area.location.store',
                                'parameters' => $this->parent->id
                            ]
                        }
                ]

            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($request);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, WarehouseArea $warehouseArea, ActionRequest $request): Response
    {
        $this->parent = $warehouseArea;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($request);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexLocations::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating location'),
                    ]
                ]
            ]
        );
    }
}
