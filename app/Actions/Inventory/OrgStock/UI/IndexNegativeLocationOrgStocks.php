<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Inventory\NegativeLocationOrgStocksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\LocationOrgStock;
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

class IndexNegativeLocationOrgStocks extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function handle(Warehouse $warehouse, string $prefix = 'negative_stocks'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value)
                    ->orWhereStartWith('locations.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(LocationOrgStock::class);
        $queryBuilder->where('location_org_stocks.warehouse_id', $warehouse->id);
        $queryBuilder->where('location_org_stocks.quantity', '<', 0);

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'location_org_stocks.id',
                'location_org_stocks.quantity',
                'org_stocks.slug',
                'org_stocks.code',
                'org_stocks.name',
                'locations.slug as location_slug',
                'locations.code as location_code',
            ])
            ->leftJoin('org_stocks', 'location_org_stocks.org_stock_id', 'org_stocks.id')
            ->leftJoin('locations', 'location_org_stocks.location_id', 'locations.id')
            ->allowedSorts(['code', 'name', 'location_code', 'quantity'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('negative stock'), __('negative stocks')])
                ->defaultSort('code')
                ->column(key: 'code', label: __('Part'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'location_code', label: __('Location'), sortable: true, searchable: true)
                ->column(key: 'quantity', label: __('Stock'), sortable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $negativeStocks): AnonymousResourceCollection
    {
        return NegativeLocationOrgStocksResource::collection($negativeStocks);
    }

    public function htmlResponse(LengthAwarePaginator $negativeStocks, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/NegativeLocationOrgStocks',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('Negative Stocks'),
                'pageHead' => [
                    'title'      => __('Negative Stocks'),
                    'icon'       => [
                        'icon'  => ['fal', 'fa-exclamation-triangle'],
                        'title' => __('Negative Stocks'),
                    ],
                    'afterTitle' => [
                        'label'   => __('These should never happen, audit the location to the real quantity'),
                    ],
                ],
                'negativeStocks' => NegativeLocationOrgStocksResource::collection($negativeStocks),
            ]
        )->table($this->tableStructure(prefix: 'negative_stocks'));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.negative_stocks.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Negative Stocks'),
                        'icon'  => 'fal fa-exclamation-triangle',
                    ],
                ],
            ]
        );
    }
}
