<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:34:29 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\WarehouseArea\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateWarehouseArea extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('create warehouse area'),
                'pageHead'    => [
                    'model'        => __('warehouse area'),
                    'title'        => __('create'),
                    'icon'         => 'fal fa-map-signs',
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('ID/Name'),
                            'fields' => [
                                'code' => [
                                    'type'        => 'input',
                                    'label'       => __('code'),
                                    'placeholder' => __('maximum 16 character long'),
                                    'value'       => '',
                                    'required'    => true,
                                ],
                                'name' => [
                                    'type'    => 'input',
                                    'label'   => __('name'),
                                    'value'   => '',
                                    'required' => true
                                ],

                            ]
                        ],

                    ],
                    'route'     => [
                        'name'       => 'grp.models.warehouse.warehouse_area.store',
                        'parameters' => $warehouse->id
                    ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($warehouse, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {

        return array_merge(
            IndexWarehouseAreas::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating warehouse areas'),
                    ]
                ]
            ]
        );
    }
}
