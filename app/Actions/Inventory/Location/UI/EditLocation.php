<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditLocation extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Location $location): Location
    {
        return $location;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, ActionRequest $request): Location
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($location);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseArea(Organisation $organisation, Warehouse $warehouse, WarehouseArea $warehouseArea, Location $location, ActionRequest $request): Location
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($location);
    }

    public function htmlResponse(Location $location, ActionRequest $request): Response
    {
        $sections               = [];
        $sections['properties'] = [
            'label'  => __('Properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'code' => [
                    'type'  => 'input',
                    'label' => __('code'),
                    'value' => $location->code
                ],
            ],
        ];

        $sections['capacity'] = [
            'label'  => __('Capacity'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'max_weight' => [
                    'type'  => 'input',
                    'label' => __('max weight'),
                    'value' => $location->max_weight
                ],
                'max_volume' => [
                    'type'  => 'input',
                    'label' => __('max volume'),
                    'value' => $location->max_volume
                ],
            ],
        ];

        $currentSection = 'properties';
        if ($request->has('section') && Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }

        return Inertia::render(
            'EditModel',
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
                    'title'   => $location->code,
                    'icon'    => [
                        'title' => __('locations'),
                        'icon'  => 'fal fa-inventory'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'current'   => $currentSection,
                    'blueprint' => $sections,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.location.update',
                            'parameters' => $location->id
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowLocation::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Location $location, ActionRequest $request): ?array
    {
        $previous = Location::where('slug', '<', $location->slug)->when(true, function ($query) use ($location, $request) {
            if ($request->route()->getName() === 'grp.org.warehouses.show.infrastructure.locations.edit') {
                $query->where('locations.warehouse_id', $location->warehouse_id);
            } else {
                $query->where('locations.warehouse_area_id', $location->warehouse_area_id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Location $location, ActionRequest $request): ?array
    {
        $next = Location::where('slug', '>', $location->slug)->when(true, function ($query) use ($location, $request) {
            if ($request->route()->getName() === 'grp.org.warehouses.show.infrastructure.locations.edit') {
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

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.locations.edit' => [
                'label' => $location->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'location' => $location->slug
                    ]

                ]
            ],
            'grp.org.warehouses.show.inventory.warehouse-areas.show.locations.edit' => [
                'label' => $location->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'warehouseArea' => $location->warehouseArea->slug,
                        'location'      => $location->slug
                    ]

                ]
            ],
            'grp.org.warehouses.show.infrastructure.locations.edit' => [
                'label' => $location->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $location->organisation->slug,
                        'warehouse'    => $location->warehouse->slug,
                        'location'     => $location->slug
                    ]

                ]
            ],
            'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.edit' => [
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
            ]
        };
    }
}
