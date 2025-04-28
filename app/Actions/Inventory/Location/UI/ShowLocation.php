<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 11:34:34 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInWarehouse;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\Inventory\WarehouseArea\UI\ShowWarehouseArea;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseAuthorisation;
use App\Enums\UI\Fulfilment\PalletDeliveryTabsEnum;
use App\Enums\UI\Inventory\LocationTabsEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowLocation extends OrgAction
{
    use WithActionButtons;
    use WithWarehouseAuthorisation;

    private WarehouseArea|Warehouse|Organisation $parent;

    public function handle(Location $location): Location
    {
        return $location;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, ActionRequest $request): Location
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(LocationTabsEnum::values());

        return $this->handle($location);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseArea(Organisation $organisation, Warehouse $warehouse, WarehouseArea $warehouseArea, Location $location, ActionRequest $request): Location
    {
        $this->parent = $warehouseArea;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(LocationTabsEnum::values());

        return $this->handle($location);
    }

    public function htmlResponse(Location $location, ActionRequest $request): Response
    {
        $navigation = LocationTabsEnum::navigation();

        if (!$location->allow_stocks) {
            unset($navigation[LocationTabsEnum::STOCKS->value]);
            unset($navigation[LocationTabsEnum::STOCK_MOVEMENTS->value]);
        }
        if (!$location->allow_fulfilment) {
            unset($navigation[LocationTabsEnum::PALLETS->value]);
        }
        if (!$location->allow_dropshipping) {
            unset($navigation[LocationTabsEnum::PALLETS->value]);
            unset($navigation[LocationTabsEnum::STOCKS->value]);
            unset($navigation[LocationTabsEnum::STOCK_MOVEMENTS->value]);
        }


        return Inertia::render(
            'Org/Warehouse/Location',
            [
                'title'       => __('location'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($location, $request),
                    'next'     => $this->getNext($location, $request),
                ],
                'pageHead'    => [
                    'icon'    => [
                        'title' => __('locations'),
                        'icon'  => 'fal fa-inventory'
                    ],
                    'model'   => __('location'),
                    'title'   => $location->slug,
                    'actions' => [
                        $this->canEdit ? $this->getEditActionIcon($request) : null,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => LocationTabsEnum::navigation()

                ],

                LocationTabsEnum::SHOWCASE->value => $this->tab == LocationTabsEnum::SHOWCASE->value ?
                    fn () => GetLocationShowcase::run($location)
                    : Inertia::lazy(fn () => GetLocationShowcase::run($location)),

                LocationTabsEnum::PALLETS->value => $this->tab == LocationTabsEnum::PALLETS->value ?
                    fn () => PalletsResource::collection(IndexPalletsInWarehouse::run($location))
                    : Inertia::lazy(fn () => PalletsResource::collection(IndexPalletsInWarehouse::run($location))),

                LocationTabsEnum::HISTORY->value => $this->tab == LocationTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($location))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($location)))
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: LocationTabsEnum::HISTORY->value))->table(
            IndexPalletsInWarehouse::make()->tableStructure(
                $location,
                prefix: PalletDeliveryTabsEnum::PALLETS->value
            )
        );
    }


    public function jsonResponse(Location $location): JsonResource
    {
        return LocationResource::make($location);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Location $location, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Locations')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $location->code,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        $location = Location::where('slug', $routeParameters['location'])->first();

        return match ($routeName) {
            'grp.org.warehouses.show.infrastructure.locations.show' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'warehouse'])),
                $headCrumb(
                    $location,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.locations.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'location'])
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show' => array_merge(
                ShowWarehouseArea::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse', 'warehouseArea'])
                ),
                $headCrumb(
                    $location,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'warehouseArea'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'warehouseArea', 'location'])
                        ]
                    ],
                    $suffix
                ),
            ),

            default => []
        };
    }

    public function getPrevious(Location $location, ActionRequest $request): ?array
    {
        $previous = Location::where('slug', '<', $location->slug)->when(true, function ($query) use ($location) {
            if ($this->parent instanceof Warehouse) {
                $query->where('locations.warehouse_id', $location->warehouse_id);
            } else {
                $query->where('locations.warehouse_area_id', $location->warehouse_area_id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Location $location, ActionRequest $request): ?array
    {
        $next = Location::where('slug', '>', $location->slug)->when(true, function ($query) use ($location) {
            if ($this->parent instanceof Warehouse) {
                $query->where('locations.warehouse_id', $location->warehouse_id);
            } else {
                $query->where('locations.warehouse_area_id', $location->warehouse_area_id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Location $location, string $routeName): ?array
    {
        if (!$location) {
            return null;
        }

        if ($this->parent instanceof Warehouse) {
            return [
                'label' => $location->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $location->organisation->slug,
                        'warehouse'    => $location->warehouse->slug,
                        'location'     => $location->slug
                    ]

                ]
            ];
        } else {
            return [
                'label' => $location->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $location->organisation->slug,
                        'warehouse'     => $location->warehouse->slug,
                        'warehouseArea' => $location->warehouseArea->slug,
                        'location'      => $location->slug
                    ]
                ]
            ];
        }
    }

}
