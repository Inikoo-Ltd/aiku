<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 17:38:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInWarehouse;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\UI\Fulfilment\PalletDeliveryTabsEnum;
use App\Enums\UI\Inventory\FulfilmentLocationTabsEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentLocation extends OrgAction
{
    use WithActionButtons;
    use WithFulfilmentWarehouseAuthorisation;


    public function handle(Location $location): Location
    {
        return $location;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, ActionRequest $request): Location
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(FulfilmentLocationTabsEnum::values());

        return $this->handle($location);
    }


    public function htmlResponse(Location $location, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Warehouse/Location',
            [
                'title'       => __('location'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($location, $request),
                    'next'     => $this->getNext($location, $request),
                ],
                'pageHead'    => [
                    'model'     => __('location'),
                    'icon'      => [
                        'title' => __('locations'),
                        'icon'  => 'fal fa-inventory'
                    ],
                    'title' => $location->slug,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentLocationTabsEnum::navigation()

                ],

                FulfilmentLocationTabsEnum::SHOWCASE->value => $this->tab == FulfilmentLocationTabsEnum::SHOWCASE->value ?
                    fn () => GetLocationShowcase::run($location)
                    : Inertia::lazy(fn () => GetLocationShowcase::run($location)),

                FulfilmentLocationTabsEnum::PALLETS->value => $this->tab == FulfilmentLocationTabsEnum::PALLETS->value ?
                    fn () => PalletsResource::collection(IndexPalletsInWarehouse::run($location))
                    : Inertia::lazy(fn () => PalletsResource::collection(IndexPalletsInWarehouse::run($location))),

                FulfilmentLocationTabsEnum::HISTORY->value => $this->tab == FulfilmentLocationTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($location))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($location)))
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: FulfilmentLocationTabsEnum::HISTORY->value))->table(
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

    public function getBreadcrumbs(array $routeParameters, string $suffix = ''): array
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


        return array_merge(
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
        );
    }

    public function getPrevious(Location $location, ActionRequest $request): ?array
    {
        $previous = Location::where('slug', '<', $location->slug)
            ->where('locations.warehouse_id', $location->warehouse_id)
            ->where('locations.allow_fulfilment', true)
            ->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Location $location, ActionRequest $request): ?array
    {
        $next = Location::where('slug', '>', $location->slug)
            ->where('locations.warehouse_id', $location->warehouse_id)
            ->where('locations.allow_fulfilment', true)
            ->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Location $location, string $routeName): ?array
    {
        if (!$location) {
            return null;
        }

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
    }

}
