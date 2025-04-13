<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 11:34:32 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Location\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Actions\UI\Fulfilment\ShowWarehouseFulfilmentDashboard;
use App\Enums\UI\Inventory\WarehouseTabsEnum;
use App\Http\Resources\Inventory\FulfilmentLocationsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentLocations extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;


    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WarehouseTabsEnum::values());

        return $this->handle(parent: $warehouse, prefix: 'locations');
    }


    public function handle(Warehouse $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('locations.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Location::class);
        $queryBuilder->where('locations.warehouse_id', $parent->id);
        $queryBuilder->where('locations.allow_fulfilment', true);

        return $queryBuilder
            ->defaultSort('locations.code')
            ->select(
                [
                    'locations.id',
                    'locations.code',
                    'locations.slug',
                    'location_stats.number_pallets',
                    'locations.allow_stocks',
                    'locations.allow_fulfilment',
                    'locations.allow_dropshipping',
                    'locations.has_stock_slots',
                    'locations.has_fulfilment',
                    'locations.has_dropshipping_slots',
                    'warehouses.slug as warehouse_slug',
                    'warehouse_areas.slug as warehouse_area_slug',
                    'warehouse_area_id'
                ]
            )
            ->leftJoin('location_stats', 'location_stats.location_id', 'locations.id')
            ->leftJoin('warehouses', 'locations.warehouse_id', 'warehouses.id')
            ->leftJoin('warehouse_areas', 'locations.warehouse_area_id', 'warehouse_areas.id')
            ->allowedSorts(['code', 'number_pallets'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(Warehouse $warehouse, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($warehouse, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($warehouse)) {
                        'Warehouse' => [
                            'title' => __("There is no locations assigned to fulfilment in this warehouse."),
                            'count' => $warehouse->stats->number_locations_allow_fulfilment,
                        ],
                        default => null
                    }
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_pallets', label: __('Pallets'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $locations): AnonymousResourceCollection
    {
        return FulfilmentLocationsResource::collection($locations);
    }


    public function htmlResponse(LengthAwarePaginator $locations, ActionRequest $request): Response
    {
        $warehouse = $request->route()->parameters()['warehouse'];

        return Inertia::render(
            'Org/Warehouse/Fulfilment/Locations',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('locations'),
                'pageHead'    => [
                    'title'     => __('locations'),
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-inventory'],
                        'title' => __('locations')
                    ],
                ],

                'data' => FulfilmentLocationsResource::collection($locations),

            ]
        )->table($this->tableStructure(warehouse: $warehouse, prefix: 'locations'));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowWarehouseFulfilmentDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.fulfilment.locations.index',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'warehouse'    => $routeParameters['warehouse'],
                            ]
                        ],
                        'label' => __('Locations'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }


}
