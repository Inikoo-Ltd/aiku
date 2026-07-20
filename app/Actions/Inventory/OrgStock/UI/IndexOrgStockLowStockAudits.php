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
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Http\Resources\Inventory\OrgStockReplenishmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
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

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation, string $prefix = 'low_stock_audits'): LengthAwarePaginator
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

        $orderedDemand = DeliveryNoteItem::query()
            ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
            ->where('delivery_note_items.organisation_id', $organisation->id)
            ->whereNotIn('delivery_note_items.state', [
                DeliveryNoteItemStateEnum::DISPATCHED->value,
                DeliveryNoteItemStateEnum::CANCELLED->value,
                DeliveryNoteItemStateEnum::OUT_OF_STOCK->value,
                DeliveryNoteItemStateEnum::NO_DISPATCHED->value,
            ])
            ->whereNotIn('delivery_notes.state', [
                DeliveryNoteStateEnum::DISPATCHED->value,
                DeliveryNoteStateEnum::CANCELLED->value,
            ])
            ->groupBy('delivery_note_items.org_stock_id')
            ->select('delivery_note_items.org_stock_id')
            ->selectRaw('SUM(delivery_note_items.quantity_required - COALESCE(delivery_note_items.quantity_dispatched, 0)) as ordered');

        $queryBuilder = QueryBuilder::for(OrgStock::class);
        $queryBuilder->where('org_stocks.organisation_id', $organisation->id);
        $queryBuilder->where('org_stocks.state', OrgStockStateEnum::ACTIVE);
        $queryBuilder->whereNotNull('org_stocks.picking_location_id');
        $queryBuilder->whereExists(function ($query) {
            $query->from('location_org_stocks as picking_los')
                ->whereColumn('picking_los.org_stock_id', 'org_stocks.id')
                ->whereColumn('picking_los.location_id', 'org_stocks.picking_location_id')
                ->whereRaw("(picking_los.settings->>'min_stock') IS NOT NULL")
                ->whereRaw("(picking_los.settings->>'max_stock') IS NOT NULL")
                ->whereRaw("picking_los.quantity <= (picking_los.settings->>'min_stock')::numeric");
        });
        $queryBuilder->leftJoinSub($orderedDemand, 'ordered_demand', 'ordered_demand.org_stock_id', '=', 'org_stocks.id');

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select('org_stocks.*')
            ->selectRaw('COALESCE(ordered_demand.ordered, 0) as ordered')
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
                ->withLabelRecord([__('low stock audit'), __('low stock audits')])
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

    public function jsonResponse(LengthAwarePaginator $lowStockAudits): AnonymousResourceCollection
    {
        return OrgStockReplenishmentsResource::collection($lowStockAudits);
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
                    'title' => __('Low Stock Audits'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clipboard-list-check'],
                        'title' => __('Low Stock Audits'),
                    ],
                ],
                'lowStockAudits' => OrgStockReplenishmentsResource::collection($lowStockAudits),
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
