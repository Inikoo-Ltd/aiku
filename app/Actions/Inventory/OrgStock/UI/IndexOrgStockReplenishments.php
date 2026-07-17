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
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Inventory\OrgStockReplenishmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStockReplenishments extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation, string $prefix = 'replenishments'): LengthAwarePaginator
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

        $orderedSubQuery = PurchaseOrderTransaction::query()
            ->selectRaw('COALESCE(SUM(purchase_order_transactions.quantity_ordered - purchase_order_transactions.quantity_dispatched), 0)')
            ->join('purchase_orders', 'purchase_order_transactions.purchase_order_id', '=', 'purchase_orders.id')
            ->whereColumn('purchase_order_transactions.org_stock_id', 'org_stocks.id')
            ->whereIn('purchase_orders.delivery_state', [
                PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value,
                PurchaseOrderDeliveryStateEnum::DISPATCHED->value,
            ])
            ->whereNotIn('purchase_orders.state', [
                PurchaseOrderStateEnum::CANCELLED->value,
                PurchaseOrderStateEnum::NOT_RECEIVED->value,
            ]);

        $queryBuilder = QueryBuilder::for(OrgStock::class);
        $queryBuilder->where('org_stocks.organisation_id', $organisation->id);
        $queryBuilder->where('org_stocks.state', OrgStockStateEnum::ACTIVE);
        $queryBuilder->whereNotNull('org_stocks.picking_location_id');
        $queryBuilder->whereExists(function ($query) use ($orderedSubQuery) {
            $query->from('location_org_stocks as picking_los')
                ->whereColumn('picking_los.org_stock_id', 'org_stocks.id')
                ->whereColumn('picking_los.location_id', 'org_stocks.picking_location_id')
                ->whereRaw("(picking_los.settings->>'min_stock') IS NOT NULL")
                ->whereRaw("(picking_los.settings->>'max_stock') IS NOT NULL")
                ->whereRaw("picking_los.quantity <= (picking_los.settings->>'min_stock')::numeric");
            $query->where($orderedSubQuery, '>', 0);
        });

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select('org_stocks.*')
            ->addSelect(['ordered' => $orderedSubQuery])
            ->with(['locationOrgStocks' => fn ($query) => $query->with('location')])
            ->allowedSorts(['code', 'quantity_available'])
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
                ->withLabelRecord([__('replenishment'), __('replenishments')])
                ->defaultSort('code')
                ->column(key: 'code', label: __('Part'), sortable: true, searchable: true)
                ->column(key: 'other_locations', label: __('Other locations stock'))
                ->column(key: 'location', label: __('Location'))
                ->column(key: 'stock', label: __('Stock'), sortable: true, align: 'right')
                ->column(key: 'ordered', label: __('Ordered'), align: 'right')
                ->column(key: 'eventual_stock', label: __('Eventual Stock'), align: 'right')
                ->column(key: 'recommended', label: __('Recommended SKOs quantity'));
        };
    }

    public function jsonResponse(LengthAwarePaginator $replenishments): AnonymousResourceCollection
    {
        return OrgStockReplenishmentsResource::collection($replenishments);
    }

    public function htmlResponse(LengthAwarePaginator $replenishments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrgStockReplenishments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('Replenishments'),
                'pageHead' => [
                    'title' => __('Replenishments'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-dolly'],
                        'title' => __('Replenishments'),
                    ],
                ],
                'replenishments' => OrgStockReplenishmentsResource::collection($replenishments),
            ]
        )->table($this->tableStructure(prefix: 'replenishments'));
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
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Replenishments'),
                        'icon'  => 'fal fa-dolly',
                    ],
                ],
            ]
        );
    }
}
