<?php

/*
 * author Louis Perez
 * created on 26-03-2026-17h-09m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\UI\Inventory\OrgStocksTabsEnum;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocksWithNoProducts extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithOrgAgentSubNavigation;
    use WithOrgPartnerSubNavigation;

    private OrgStockFamily|Organisation $parent;

    private string $bucket;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle(parent: $organisation, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    public function current(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'current';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function active(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->parent = $organisation;
        $this->initialisation($this->parent, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    public function inProcess(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    public function discontinuing(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinuing';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    public function discontinued(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    public function abnormality(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'abnormality';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: OrgStocksTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $orgStockFamily;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $orgStockFamily);
    }

    protected function getElementGroups(Organisation|OrgStockFamily $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OrgStockStateEnum::labels(),
                    OrgStockStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('org_stocks.state', $elements);
                },

            ],
        ];
    }

    public function handle(OrgStockFamily|Organisation $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

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

        if ($parent instanceof OrgStockFamily) {
            $queryBuilder->where('org_stock_family_id', $parent->id);
            $queryBuilder->addSelect([
                'org_stock_families.slug as family_slug',
                'org_stock_families.code as family_code',
            ]);
        } else {
            $queryBuilder->where('org_stocks.organisation_id', $this->organisation->id);
        }

        if ($this->bucket == 'current') {
            $queryBuilder->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE, OrgStockStateEnum::DISCONTINUING]);
        } elseif ($this->bucket == 'active') {
            $queryBuilder->where('org_stocks.state', OrgStockStateEnum::ACTIVE);
        } elseif ($this->bucket == 'discontinuing') {
            $queryBuilder->where('org_stocks.state', OrgStockStateEnum::DISCONTINUING);
        } elseif ($this->bucket == 'discontinued') {
            $queryBuilder->where('org_stocks.state', OrgStockStateEnum::DISCONTINUED);
        } elseif ($this->bucket == 'abnormality') {
            $queryBuilder->where('org_stocks.state', OrgStockStateEnum::ABNORMALITY);
        } elseif (!($parent instanceof Group)) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        $queryBuilder->whereRaw(
            "
            (
                SELECT COUNT(*)
                FROM product_has_org_stocks
                WHERE org_stock_id = org_stocks.id
            ) = 0
        "
        );

        $selects = [
            'org_stocks.id',
            'org_stocks.code',
            'org_stocks.name',
            'org_stocks.slug',
            'org_stocks.state',
            'org_stocks.sku_value',
            'org_stocks.current_supplier_sku_cost',
            'org_stocks.quantity_available',
            'org_stocks.value_in_locations',
            'number_locations',
            'quantity_in_locations',
            'org_stocks.discontinued_in_organisation_at',
            'org_stocks.health_rank',
            'org_stock_families.slug as family_slug',
            'org_stock_families.code as family_code',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'currencies.code as currency_code',
            'warehouses.slug as warehouse_slug',
            'org_stock_intervals.dispatched_ytd as dispatched',
            'org_stock_sales_intervals.revenue_org_currency_ytd as revenue',
            DB::raw(
                "(
                SELECT COALESCE(SUM(os2.quantity_in_locations), 0)
                FROM org_stocks os2
                INNER JOIN model_has_trade_units mhtu2 ON mhtu2.model_id = os2.id AND mhtu2.model_type = 'OrgStock'
                WHERE mhtu2.trade_unit_id IN (
                    SELECT mhtu.trade_unit_id
                    FROM model_has_trade_units mhtu
                    WHERE mhtu.model_id = org_stocks.id
                    AND mhtu.model_type = 'OrgStock'
                )
            ) as stock_value"
            ),
            DB::raw(
                "(
                SELECT COALESCE(SUM(pot.org_net_amount), 0)
                FROM purchase_order_transactions pot
                INNER JOIN purchase_orders po ON pot.purchase_order_id = po.id
                WHERE pot.org_stock_id = org_stocks.id
                AND po.delivery_state IN ('ready_to_ship', 'dispatched')
                AND po.state NOT IN ('cancelled', 'not_received')
            ) as on_the_way_po_value"
            ),
            DB::raw(
                "(
                SELECT COUNT(DISTINCT po.id)
                FROM purchase_order_transactions pot
                INNER JOIN purchase_orders po ON pot.purchase_order_id = po.id
                WHERE pot.org_stock_id = org_stocks.id
                AND po.delivery_state IN ('ready_to_ship', 'dispatched')
                AND po.state NOT IN ('cancelled', 'not_received')
            ) as on_the_way_po_count"
            ),
            DB::raw(
                "(
                SELECT
                    CASE
                        WHEN SUM(it.quantity) > 0 THEN
                            org_stocks.quantity_available
                            * EXTRACT(EPOCH FROM (NOW() - MIN(it.date))) / (7.0 * 86400)
                            / SUM(it.quantity)
                        ELSE NULL
                    END
                FROM invoice_transactions it
                INNER JOIN invoice_transaction_has_org_stocks ithos ON ithos.invoice_transaction_id = it.id
                WHERE ithos.org_stock_id = org_stocks.id
                AND it.deleted_at IS NULL
            ) as woc"
            ),
            DB::raw(
                "(
                SELECT COUNT(*)
                FROM product_has_org_stocks
                WHERE org_stock_id = org_stocks.id
            ) as product_count"
            )
        ];

        if ($prefix === OrgStocksTabsEnum::SALES->value) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'org_stock_time_series',
                timeSeriesRecordsTable: 'org_stock_time_series_records',
                foreignKey: 'org_stock_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'invoices'                    => 'invoices',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true
            );

            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external'];
            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
        }

        $allowedSorts = [
            'code',
            'name',
            'family_code',
            'sku_value',
            'current_supplier_sku_cost',
            'stock_value',
            'discontinued_in_organisation_at',
            'organisation_name',
            'value_in_locations',
            'dispatched',
            'revenue',
            'quantity_available',
            'on_the_way_po_value',
            'health_rank',
            'woc',
            'product_count'
        ];

        if ($prefix === OrgStocksTabsEnum::SALES->value) {
            $allowedSorts[] = 'sales_grp_currency_external';
            $allowedSorts[] = 'invoices';
        }

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select($selects)
            ->leftJoin('organisations', 'org_stocks.organisation_id', 'organisations.id')
            ->leftJoin('currencies', 'organisations.currency_id', 'currencies.id')
            ->leftJoin('warehouses', 'warehouses.organisation_id', 'organisations.id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoin('org_stock_families', 'org_stocks.org_stock_family_id', 'org_stock_families.id')
            ->leftJoin('org_stock_intervals', 'org_stock_intervals.org_stock_id', 'org_stocks.id')
            ->leftJoin('org_stock_sales_intervals', 'org_stock_sales_intervals.org_stock_id', 'org_stocks.id')
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(OrgStockFamily|Organisation $parent, ?array $modelOperations = null, $prefix = null, $bucket = null, bool $sales = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $bucket, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($bucket == 'all') {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->defaultSort('code')
                ->withLabelRecord([__('sku'), __('skus')])
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'code', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Organisation && $bucket != 'abnormality') {
                $table->column(key: 'family_code', label: __('Family'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity_available', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true);

            if ($sales) {
                $table->betweenDates(['date'])
                    ->column(key: 'stock_value', label: __('Stock Value'), canBeHidden: false, sortable: true, type: 'currency')
                    ->column(key: 'on_the_way_po_value', label: __("On the way (PO's)"), sortable: true, type: 'currency')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right')
                    ->column(key: 'sales_grp_currency_external', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'sales_grp_currency_external_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right')
                    ->column(key: 'health_rank', label: __('Health'), canBeHidden: false, sortable: true, type: 'icon');
            } else {
                if ($parent instanceof OrgStockFamily || !$bucket || in_array($bucket, ['active', 'discontinuing'])) {
                    $table
                        ->column(key: 'sku_value', label: __('Sku value'), canBeHidden: false, sortable: true, type: 'currency')
                        ->column(key: 'woc', label: __('WOC'), canBeHidden: false, align: 'right')
                        ->column(key: 'revenue', label: __('Revenue'), sortable: true, type: 'currency')
                        ->column(key: 'dispatched', label: __('Dispatched'), sortable: true);
                }

                if ($bucket == 'discontinued' || $bucket == 'abnormality') {
                    $table->column(key: 'discontinued_in_organisation_at', label: $bucket == 'discontinued' ? __('Discontinued') : __('Last seen'), canBeHidden: false, sortable: true, searchable: true, type: 'date');
                }
            }
            $table->column(key: 'product_count', label: __('Used in Products'), canBeHidden: false, sortable: true, searchable: true, align: 'left');
        };
    }

    public function jsonResponse(LengthAwarePaginator $stocks): AnonymousResourceCollection
    {
        return OrgStocksResource::collection($stocks);
    }

    public function getOrgStocksSubNavigation(): array
    {
        if ($this->parent instanceof Organisation) {
            $stats = $this->parent->inventoryStats;
        } else {
            $stats = $this->parent->stats;
        }

        return [

            [
                'label'  => __('Current'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.current',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.current',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug,
                    ],
                ],
                'number' => $stats->number_current_org_stocks,
            ],

            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.discontinued',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.discontinued',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug,
                    ],
                ],
                'align'  => 'right',
                'number' => $stats->number_org_stocks_state_discontinued,
            ],
            [
                'label'  => __('Abnormalities'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.abnormality',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.abnormality',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug,
                    ],
                ],
                'align'  => 'right',
                'number' => $stats->number_org_stocks_state_abnormality,
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.index',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug,
                    ],
                ],
                'number' => $stats->number_org_stocks,
                'align'  => 'right',
            ],

        ];
    }

    public function htmlResponse(LengthAwarePaginator $stocks, ActionRequest $request): Response
    {
        $title      = __('SKUs');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-box'],
            'title' => __('SKUs'),
        ];
        $afterTitle = null;
        $iconRight  = null;

        $subNavigation = $this->getOrgStocksSubNavigation();

        if ($this->bucket == 'current') {
            $title = __('Current SKUs');
        }

        return Inertia::render(
            'Org/Inventory/OrgStocks',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    '('.__('Orphan/No Product').')'
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgStocksTabsEnum::navigation(),
                ],

                OrgStocksTabsEnum::INDEX->value => $this->tab == OrgStocksTabsEnum::INDEX->value
                    ? fn () => OrgStocksResource::collection($stocks)
                    : Inertia::lazy(fn () => OrgStocksResource::collection($stocks)),

                OrgStocksTabsEnum::SALES->value => $this->tab == OrgStocksTabsEnum::SALES->value
                    ? fn () => OrgStocksResource::collection($this->handle(parent: $this->parent, prefix: OrgStocksTabsEnum::SALES->value, bucket: $this->bucket))
                    : Inertia::lazy(fn () => OrgStocksResource::collection($this->handle(parent: $this->parent, prefix: OrgStocksTabsEnum::SALES->value, bucket: $this->bucket))),
            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: OrgStocksTabsEnum::INDEX->value, bucket: $this->bucket))
            ->table($this->tableStructure(parent: $this->parent, prefix: OrgStocksTabsEnum::SALES->value, bucket: $this->bucket, sales: true));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => 'SKUs',
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stocks.orphan-product.index' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.orphan-product.current' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    trim('('.__('Current').') '.$suffix)
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.orphan-product.discontinued' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    trim('('.__('Discontinued').') '.$suffix)
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.orphan-product.abnormalities' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    trim('('.__('Abnormalities').') '.$suffix)
                )
            ),
            default => []
        };
    }
}
