<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 08:39:50 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\WarehouseArea\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\Location\UI\IndexLocations;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseAuthorisation;
use App\Enums\UI\Inventory\WarehouseAreaTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\LocationResource;
use App\Http\Resources\Inventory\WarehouseAreaResource;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehouseArea extends OrgAction
{
    use WithActionButtons;
    use WithWarehouseAuthorisation;


    public function maya(Organisation $organisation, WarehouseArea $warehouseArea, ActionRequest $request): WarehouseArea
    {
        $this->maya = true;
        $this->initialisation($organisation, $request)->withTab(WarehouseAreaTabsEnum::values());

        return $this->handle($warehouseArea);
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, WarehouseArea $warehouseArea, ActionRequest $request): WarehouseArea
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WarehouseAreaTabsEnum::values());

        return $warehouseArea;
    }

    public function handle(WarehouseArea $warehouseArea): WarehouseArea
    {
        return $warehouseArea;
    }

    public function htmlResponse(WarehouseArea $warehouseArea, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Warehouse/WarehouseArea',
            [
                'title'                                => __('warehouse area'),
                'breadcrumbs'                          => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'                           => [
                    'previous' => $this->getPrevious($warehouseArea, $request),
                    'next'     => $this->getNext($warehouseArea, $request),
                ],
                'pageHead'                             => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-map-signs'],
                            'title' => __('warehouse area')
                        ],
                    'title'   => $warehouseArea->name,
                    'actions' => [
                        $this->canEdit ?
                            [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new location'),
                                'label'   => __('new location'),
                                'route'   => [
                                    'name'       => $request->route()->getName().'.locations.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            ]
                            : null,
                        $this->canEdit ? $this->getEditActionIcon($request) : null,
                    ],
                    'meta'    => [
                        [
                            'name'     => trans_choice('location|locations', $warehouseArea->stats->number_locations),
                            'number'   => $warehouseArea->stats->number_locations,
                            'route'    => [
                                'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-inventory',
                                'tooltip' => __('locations')
                            ]
                        ]
                    ]
                ],
                'tabs'                                 => [
                    'current'    => $this->tab,
                    'navigation' => WarehouseAreaTabsEnum::navigation()
                ],
                WarehouseAreaTabsEnum::SHOWCASE->value => $this->tab == WarehouseAreaTabsEnum::SHOWCASE->value ?
                    fn () => GetWarehouseAreaShowcase::run($warehouseArea)
                    : Inertia::lazy(fn () => GetWarehouseAreaShowcase::run($warehouseArea)),

                WarehouseAreaTabsEnum::LOCATIONS->value => $this->tab == WarehouseAreaTabsEnum::LOCATIONS->value
                    ?
                    fn () => LocationResource::collection(
                        IndexLocations::run(
                            parent: $warehouseArea,
                            prefix: WarehouseAreaTabsEnum::LOCATIONS->value
                        )
                    )
                    : Inertia::lazy(fn () => LocationResource::collection(
                        IndexLocations::run(
                            parent: $warehouseArea,
                            prefix: WarehouseAreaTabsEnum::LOCATIONS->value
                        )
                    )),

                WarehouseAreaTabsEnum::HISTORY->value => $this->tab == WarehouseAreaTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($warehouseArea))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($warehouseArea)))

            ]
        )->table(
            IndexLocations::make()->tableStructure(
                parent: $warehouseArea,
                prefix: WarehouseAreaTabsEnum::LOCATIONS->value
            )
        )->table(IndexHistory::make()->tableStructure(prefix: WarehouseAreaTabsEnum::HISTORY->value));
    }


    public function jsonResponse(WarehouseArea $warehouseArea): WarehouseAreaResource
    {
        return new WarehouseAreaResource($warehouseArea);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (WarehouseArea $warehouseArea, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Warehouse areas')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $warehouseArea->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        $warehouseArea = WarehouseArea::where('slug', $routeParameters['warehouseArea'])->first();

        return array_merge(
            (new ShowWarehouse())->getBreadcrumbs($routeParameters),
            $headCrumb(
                $warehouseArea,
                [
                    'index' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'warehouseArea'])
                    ]
                ],
                $suffix
            )
        );
    }

    public function getPrevious(WarehouseArea $warehouseArea, ActionRequest $request): ?array
    {
        $previous = WarehouseArea::where('code', '<', $warehouseArea->code)->when(true, function ($query) use ($warehouseArea) {
            $query->where('warehouse_id', $warehouseArea->warehouse_id);
        })->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(WarehouseArea $warehouseArea, ActionRequest $request): ?array
    {
        $next = WarehouseArea::where('code', '>', $warehouseArea->code)->when(true, function ($query) use ($warehouseArea) {
            $query->where('warehouse_id', $warehouseArea->warehouse->id);
        })->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?WarehouseArea $warehouseArea, string $routeName): ?array
    {
        if (!$warehouseArea) {
            return null;
        }

        return [
            'label' => $warehouseArea->name,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'organisation'  => $warehouseArea->organisation->slug,
                    'warehouse'     => $warehouseArea->warehouse->slug,
                    'warehouseArea' => $warehouseArea->slug
                ]

            ]
        ];
    }

}
