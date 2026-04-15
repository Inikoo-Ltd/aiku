<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 May 2024 13:09:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexReturnedPalletsInWarehouse extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;
    use WithPalletsSubNavigation;

    private bool $selectStoredPallets = false;


    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);
        $query->where('pallets.warehouse_id', $warehouse->id);

        $query->where('pallets.status', PalletStatusEnum::RETURNED);

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

        $query->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallets.fulfilment_customer_id');
        $query->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id');
        $query->addSelect('customers.name as fulfilment_customer_name', 'customers.slug as fulfilment_customer_slug');

        return $query->allowedSorts(['customer_reference', 'reference', 'fulfilment_customer_name'])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Warehouse $warehouse, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $warehouse) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => '',
                'count' => $warehouse->stats->number_pallets
            ];

            $emptyStateData['description'] = __("There isn't any returned pallet in this warehouse");

            $table->withGlobalSearch();

            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'fulfilment_customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), canBeHidden: false, sortable: true, searchable: true);


            $table->defaultSort('reference');
        };
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }

    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        /** @var Warehouse $warehouse */
        $warehouse = $request->route()->parameter('warehouse');
        return Inertia::render(
            'Org/Fulfilment/Pallets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallets'),
                'pageHead'    => [
                    'title'         => __('Returned pallets'),
                    'icon'          => ['fal', 'fa-pallet'],
                    'subNavigation' => $this->getPalletsInWarehouseSubNavigation($warehouse, $request)

                ],
                'data'        => PalletsResource::collection($pallets),
            ]
        )->table($this->tableStructure($warehouse, 'pallets'));
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);
        return $this->handle($warehouse, 'pallets');
    }



    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.warehouses.show.inventory.pallets.returned.index', 'grp.org.warehouses.show.inventory.pallets.returned.show' =>
            array_merge(
                ShowWarehouse::make()->getBreadcrumbs($routeParameters),
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
                            'label' => __('Returned pallets'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),
        };
    }
}
