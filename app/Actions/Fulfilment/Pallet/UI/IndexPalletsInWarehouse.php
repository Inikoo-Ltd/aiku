<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 May 2024 13:09:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Actions\UI\Fulfilment\ShowWarehouseFulfilmentDashboard;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Pallet;
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

class IndexPalletsInWarehouse extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;
    use WithPalletsSubNavigation;


    private Warehouse|Location $parent;


    public function handle(Warehouse|Location $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereStartWith('pallets.reference', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);

        if (class_basename($parent) == 'Location') {
            $query->where('location_id', $parent->id);
        } else {
            $query->where('pallets.warehouse_id', $parent->id);
        }

        $query->whereIn('pallets.status', ['receiving', 'storing', 'returning']);


        $query->defaultSort('pallets.id')
            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallets.state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.received_at',
                'pallets.location_id',
                'pallets.fulfilment_customer_id',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id'
            );

        if (!$parent instanceof Location) {
            $query->leftJoin('locations', 'locations.id', 'pallets.location_id');
            $query->addSelect('locations.code as location_code', 'locations.slug as location_slug');
        }


        return $query->allowedSorts(['customer_reference', 'reference', 'location_code'])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Warehouse|Location $parent, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table->withGlobalSearch();

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => '',
                'count' => $parent->stats->number_pallets
            ];


            if ($parent instanceof Warehouse) {
                $emptyStateData['description'] = __("There isn't any fulfilment pallet in this warehouse");
            }


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);


            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), canBeHidden: false, sortable: true, searchable: true);


            if ($parent instanceof Warehouse) {
                $table->column(key: 'location_code', label: __('Location'), canBeHidden: false, sortable: true, searchable: true);
            }


            $table->column(key: 'contents', label: __('Contents'), canBeHidden: false, searchable: true);
            $table->column(key: 'actions', label: __('Actions'), canBeHidden: false, searchable: true);
            $table->defaultSort('reference');
        };
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }

    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Warehouse/Fulfilment/Pallets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('Pallets'),
                'pageHead'    => [
                    'title'         => __('Pallets in warehouse'),
                    'icon'          => ['fal', 'fa-pallet'],
                    'subNavigation' => $this->getPalletsInWarehouseSubNavigation($this->parent, $request)

                ],
                'data'        => PalletsResource::collection($pallets),
            ]
        )->table($this->tableStructure($this->parent, 'pallets'));
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, 'pallets');
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inLocation(Organisation $organisation, Warehouse $warehouse, Location $location, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $location;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($location);
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
                            'name'       => 'grp.org.warehouses.show.inventory.pallets.current.index',
                            'parameters' => [
                                'organisation' => $routeParameters['organisation'],
                                'warehouse'    => $routeParameters['warehouse'],
                            ]
                        ],
                        'label' => __('Pallets'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
