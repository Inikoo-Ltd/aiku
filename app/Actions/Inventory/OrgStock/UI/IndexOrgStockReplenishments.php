<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 17 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\OrgStock\WithOrgStockReplenishments;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Http\Resources\Inventory\OrgStockReplenishmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexOrgStockReplenishments extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithOrgStockReplenishments;

    private string $scope = self::SCOPE_WHOLESALE;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = self::SCOPE_WHOLESALE;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function dropshipping(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = self::SCOPE_DROPSHIPPING;
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

        $queryBuilder = QueryBuilder::for(OrgStock::class);

        $this->applyReplenishmentsConstraints($queryBuilder, $organisation, $this->scope);

        $queryBuilder->leftJoin('locations as active_location_location', 'active_location_location.id', '=', 'active_location.location_id');

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select('org_stocks.*')
            ->addSelect([
                'active_location.location_id as active_location_id',
                'active_location.quantity as stock',
                'active_location_location.code as active_location_code',
            ])
            ->selectSub(
                DB::table('delivery_note_items')
                    ->selectRaw('coalesce(sum(quantity_required - coalesce(quantity_picked, 0)), 0)')
                    ->whereColumn('delivery_note_items.org_stock_id', 'org_stocks.id')
                    ->whereIn('delivery_note_items.state', [
                        DeliveryNoteItemStateEnum::UNASSIGNED,
                        DeliveryNoteItemStateEnum::QUEUED,
                        DeliveryNoteItemStateEnum::HANDLING,
                        DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
                    ]),
                'pending_picking'
            )
            ->with(['locationOrgStocks' => fn ($query) => $query->with('location')])
            ->allowedSorts([
                'code',
                'stock',
                AllowedSort::field('location', 'active_location_location.code'),
                AllowedSort::field('pending_picking', 'pending_picking'),
            ])
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
                ->column(key: 'location', label: __('Picking location'), sortable: true)
                ->column(key: 'stock', label: __('Stock'), sortable: true, align: 'right')
                ->column(key: 'pending_picking', label: __('Picking'), sortable: true, align: 'right')
                ->column(key: 'recommended', label: __('Recommended SKOs quantity'));
        };
    }

    public function jsonResponse(LengthAwarePaginator $replenishments): AnonymousResourceCollection
    {
        return OrgStockReplenishmentsResource::collection($replenishments);
    }

    public function getSubNavigation(array $routeParameters): array
    {
        return [
            [
                'label'    => __('Wholesale'),
                'leftIcon' => ['icon' => 'fas fa-dolly-flatbed-empty'],
                'root'     => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.index',
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.index',
                    'parameters' => $routeParameters,
                ],
            ],
            [
                'label'    => __('Dropshipping'),
                'leftIcon' => ['icon' => 'fas fa-shopping-basket'],
                'root'     => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.dropshipping',
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.dropshipping',
                    'parameters' => $routeParameters,
                ],
            ],
        ];
    }

    public function htmlResponse(LengthAwarePaginator $replenishments, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        $title = $this->scope == self::SCOPE_DROPSHIPPING
            ? __('Dropshipping replenishments')
            : __('Wholesale replenishments');

        return Inertia::render(
            'Org/Inventory/OrgStockReplenishments',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $routeParameters),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-dolly'],
                        'title' => $title,
                    ],
                    'subNavigation' => $this->getSubNavigation($routeParameters),
                ],
                'replenishments' => OrgStockReplenishmentsResource::collection($replenishments),
            ]
        )->table($this->tableStructure(prefix: 'replenishments'));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $label = $routeName == 'grp.org.warehouses.show.inventory.org_stocks.replenishments.dropshipping'
            ? __('Dropshipping replenishments')
            : __('Wholesale replenishments');

        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => $label,
                        'icon'  => 'fal fa-dolly',
                    ],
                ],
            ]
        );
    }
}
