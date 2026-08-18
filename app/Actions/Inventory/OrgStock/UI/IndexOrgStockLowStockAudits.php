<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 17 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Http\Resources\Inventory\OrgStockLowStockAuditsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
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

class IndexOrgStockLowStockAudits extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation, $warehouse);
    }

    public function handle(Organisation $organisation, Warehouse $warehouse, string $prefix = 'low_stock_audits'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgStock::class);
        $queryBuilder->where('org_stocks.organisation_id', $organisation->id);
        $queryBuilder->where('org_stocks.state', OrgStockStateEnum::ACTIVE);
        $queryBuilder->where('org_stocks.quantity_in_locations', '>', 0);
        $queryBuilder->where('org_stocks.quantity_in_locations', '<', $warehouse->getLowStockThreshold());

        // A SKO drops off the list once every one of its locations in this warehouse has been counted
        $queryBuilder->whereExists(function ($query) use ($warehouse) {
            $query->selectRaw(1)
                ->from('location_org_stocks')
                ->whereColumn('location_org_stocks.org_stock_id', 'org_stocks.id')
                ->where('location_org_stocks.warehouse_id', $warehouse->id)
                ->where('location_org_stocks.is_low_stock_checked', false);
        });

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.slug',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.quantity_in_locations as stock',
                'org_stock_families.code as family_code',
                'org_stock_families.slug as family_slug',
            ])
            ->leftJoin('org_stock_families', 'org_stocks.org_stock_family_id', 'org_stock_families.id')
            ->with(['locationOrgStocks' => function ($query) use ($warehouse) {
                $query->where('location_org_stocks.warehouse_id', $warehouse->id)
                    ->join('locations', 'location_org_stocks.location_id', 'locations.id')
                    ->select([
                        'location_org_stocks.id',
                        'location_org_stocks.org_stock_id',
                        'location_org_stocks.quantity',
                        'location_org_stocks.audited_at',
                        'location_org_stocks.is_low_stock_checked',
                        'locations.code as location_code',
                    ])
                    ->orderBy('locations.code');
            }])
            ->allowedSorts(['code', 'name', 'family_code', 'stock'])
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
                ->withLabelRecord([__('low stock audit'), __('low stock audits')])
                ->defaultSort('code')
                ->column(key: 'code', label: __('Part'), sortable: true, searchable: true)
                ->column(key: 'family_code', label: __('Family'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'stock', label: __('Stock'), sortable: true, align: 'right')
                ->column(key: 'locations', label: __('Locations'));
        };
    }

    public function jsonResponse(LengthAwarePaginator $lowStockAudits): AnonymousResourceCollection
    {
        return OrgStockLowStockAuditsResource::collection($lowStockAudits);
    }

    public function htmlResponse(LengthAwarePaginator $lowStockAudits, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrgStockLowStockAudits',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('Low Stock Audits'),
                'pageHead' => [
                    'title'      => __('Low Stock Audits'),
                    'icon'       => [
                        'icon'  => ['fal', 'fa-clipboard-list-check'],
                        'title' => __('Low Stock Audits'),
                    ],
                    'afterTitle' => [
                        'label'   => __('Threshold').': '.trimDecimalZeros($this->warehouse->getLowStockThreshold()),
                        'tooltip' => __('SKOs with total stock in all locations below this threshold'),
                    ],
                ],
                'lowStockAudits' => OrgStockLowStockAuditsResource::collection($lowStockAudits),
                'auditRoute'     => [
                    'name'   => 'grp.models.location_org_stock.audit',
                    'method' => 'patch',
                ],
            ]
        )->table($this->tableStructure(prefix: 'low_stock_audits'));
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
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.low_stock_audits.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Low Stock Audits'),
                        'icon'  => 'fal fa-clipboard-list-check',
                    ],
                ],
            ]
        );
    }
}
