<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 15:27:25 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\OrgStockFamily\UI\ShowOrgStockFamily;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
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
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocks extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithOrgAgentSubNavigation;
    use WithOrgPartnerSubNavigation;

    private OrgStockFamily|Organisation|OrgPartner|OrgAgent $parent;

    private string $bucket;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle(parent: $organisation, prefix: $this->tab);
    }

    public function maya(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->maya   = true;
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle(parent: $organisation, prefix: $this->tab);
    }

    public function current(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'current';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: $this->tab);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function active(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: $this->tab);
    }

    public function inProcess(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: $this->tab);
    }

    public function discontinuing(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinuing';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: $this->tab);
    }

    public function discontinued(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: $this->tab);
    }

    public function abnormality(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'abnormality';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent, prefix: $this->tab);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $orgStockFamily;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle(parent: $orgStockFamily, prefix: $this->tab);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $orgAgent);
    }

    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $orgPartner);
    }

    protected function getElementGroups(Organisation|OrgStockFamily|OrgPartner|OrgAgent $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'default'  => OrgStockStateEnum::ACTIVE->value.','.OrgStockStateEnum::DISCONTINUING->value,
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

    public function handle(OrgStockFamily|Organisation|OrgAgent|OrgPartner $parent, $prefix = null, $bucket = null): LengthAwarePaginator
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
            $organisationId = $parent->organisation_id;
            $queryBuilder->where('org_stock_family_id', $parent->id);
            $queryBuilder->addSelect([
                'org_stock_families.slug as family_slug',
                'org_stock_families.code as family_code',
            ]);
        } elseif ($parent instanceof OrgAgent) {
            $organisationId = $parent->agent->organisation->id;
            $queryBuilder->where('org_stocks.organisation_id', $organisationId);
        } elseif ($parent instanceof OrgPartner) {
            $organisationId = $parent->partner->id;
            $queryBuilder->where('org_stocks.organisation_id', $organisationId);
        } else {
            $organisationId = $this->organisation->id;
            $queryBuilder->where('org_stocks.organisation_id', $organisationId);
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
                    prefix: $prefix,
                    default: $elementGroup['default'] ?? null,
                );
            }
        }

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
            'org_stock_stats.stock_value',
            'org_stock_stats.stock_commercial_value as potential_sales',
            'org_stock_stats.on_the_way_po_value',
            'org_stock_stats.on_the_way_po_count',
            'org_stock_stats.week_of_cover as woc',
            'org_stock_stats.number_products as product_count',
        ];

        if ($prefix === OrgStocksTabsEnum::SALES->value) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'org_stock_time_series',
                timeSeriesRecordsTable: 'org_stock_time_series_records',
                foreignKey: 'org_stock_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'cogs_grp_currency'           => 'cogs_grp_currency',
                    'invoices'                    => 'invoices',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
            );

            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external'];
            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
            $selects[] = $this->grossProfitSelect($timeSeriesData['alias']);
            $selects[] = $this->grossProfitPercentageSelect($timeSeriesData['alias']);
        } else {
            $selects[] = $this->stockCoverSelect($this->joinTrailingYearCogs($queryBuilder, $organisationId), 365);
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
            'quantity_available',
            'potential_sales',
            'on_the_way_po_value',
            'health_rank',
            'week_of_cover',
            'product_count',
        ];

        if ($prefix === OrgStocksTabsEnum::SALES->value) {
            $allowedSorts[] = 'sales_grp_currency_external';
            $allowedSorts[] = 'gross_profit';
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
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function joinTrailingYearCogs(QueryBuilder $queryBuilder, int $organisationId): string
    {
        $alias = 'cogs_trailing_year';

        $subQuery = DB::table('org_stock_time_series')
            ->join(
                'org_stock_time_series_records',
                'org_stock_time_series_records.org_stock_time_series_id',
                '=',
                'org_stock_time_series.id'
            )
            ->join('org_stocks', 'org_stocks.id', '=', 'org_stock_time_series.org_stock_id')
            ->where('org_stocks.organisation_id', $organisationId)
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('org_stock_time_series_records.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
            ->where('org_stock_time_series_records.from', '>=', now()->subYear()->startOfMonth())
            ->groupBy('org_stock_time_series.org_stock_id')
            ->select('org_stock_time_series.org_stock_id')
            ->selectRaw('COALESCE(SUM(org_stock_time_series_records.cogs_org_currency), 0) as cogs_org_currency');

        $queryBuilder->leftJoinSub(
            $subQuery,
            $alias,
            fn ($join) => $join->on("$alias.org_stock_id", '=', 'org_stocks.id')
        );

        return $alias;
    }

    protected function grossProfitSelect(string $alias): Expression
    {
        return DB::raw(
            "COALESCE($alias.sales_grp_currency_external, 0) - COALESCE($alias.cogs_grp_currency, 0) as gross_profit"
        );
    }

    protected function grossProfitPercentageSelect(string $alias): Expression
    {
        return DB::raw(
            "CASE WHEN COALESCE($alias.sales_grp_currency_external, 0) <> 0"
            ." THEN ROUND(((COALESCE($alias.sales_grp_currency_external, 0) - COALESCE($alias.cogs_grp_currency, 0)) / $alias.sales_grp_currency_external * 100)::numeric, 1)"
            .' ELSE NULL END as gross_profit_percentage'
        );
    }

    protected function stockCoverSelect(string $alias, int $days): Expression
    {
        return DB::raw(
            "CASE WHEN org_stock_stats.stock_value > 0 AND COALESCE($alias.cogs_org_currency, 0) > 0"
            ." THEN org_stock_stats.stock_value * 12 * $days / ($alias.cogs_org_currency * 365)"
            .' ELSE NULL END as stock_cover'
        );
    }

    public function tableStructure(OrgStockFamily|Organisation|OrgPartner|OrgAgent $parent, ?array $modelOperations = null, $prefix = null, $bucket = null, bool $sales = false): Closure
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
                        elements: $elementGroup['elements'],
                        default: $elementGroup['default'] ?? null,
                    );
                }
            }

            $table
                ->defaultSort('code')
                ->withLabelRecord(['SKO', 'SKOs'])
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'code', label: __('Reference'), sortable: true, searchable: true);

            if ($parent instanceof Organisation && $bucket != 'abnormality') {
                $table->column(key: 'family_code', label: __('Family'), sortable: true, searchable: true);
            }

            $table->column(key: 'name', label: __('Name'), sortable: true, searchable: true);

            if ($sales) {
                $table->betweenDates(['date'])
                    ->column(key: 'invoices', label: __('Invoices'), sortable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), align: 'right')
                    ->column(key: 'sales_grp_currency_external', label: __('Sales'), sortable: true, align: 'right')
                    ->column(key: 'sales_grp_currency_external_delta', label: __('Δ 1Y'), align: 'right')
                    ->column(key: 'gross_profit', label: __('Gross Profit'), sortable: true, align: 'right')
                    ->column(key: 'health_rank', label: __('Health'), sortable: true, type: 'icon');
            } else {
                $table
                    ->column(key: 'product_count', label: __('Products'), canBeHidden: false, sortable: true)
                    ->column(key: 'quantity_available', label: __('Stock'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'stock_value', label: __('Stock Value'), tooltip: __('Valued with FIFO — recommended, the official valuation'), tooltipIcon: true, canBeHidden: false, sortable: true, type: 'currency')
                    ->column(key: 'potential_sales', label: __('Potential Sales'), canBeHidden: false, sortable: true, type: 'currency')
                    ->column(key: 'on_the_way_po_value', label: __("On The Way (PO's)"), canBeHidden: false, sortable: true, type: 'currency')
                    ->column(key: 'stock_cover', label: __('Cover'), canBeHidden: false, sortable: false, align: 'right');

                if ($bucket == 'discontinued' || $bucket == 'abnormality') {
                    $table->column(key: 'discontinued_in_organisation_at', label: $bucket == 'discontinued' ? __('Discontinued') : __('Last seen'), sortable: true, searchable: true, type: 'date');
                }
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $stocks): AnonymousResourceCollection
    {
        return OrgStocksResource::collection($stocks);
    }

    public function getOrgStockFamilySubNavigation(OrgStockFamily $orgStockFamily, ActionRequest $request): array
    {
        $routeParameters = $request->route()->originalParameters();

        return [
            [
                'label'    => __('SKO Family'),
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show',
                    'parameters' => array_diff_key($routeParameters, ['orgStock' => null]),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-boxes-alt'],
                    'tooltip' => __('SKO Family'),
                ],
            ],
            [
                'isAnchor' => true,
                'label'    => __('SKOs'),
                'number'   => $orgStockFamily->stats->number_org_stocks ?? 0,
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index',
                    'parameters' => array_diff_key($routeParameters, ['orgStock' => null]),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-box'],
                    'tooltip' => __('SKOs'),
                ],
            ],
            [
                'label'    => __('Invoices'),
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.invoices',
                    'parameters' => array_diff_key($routeParameters, ['orgStock' => null]),
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-file-invoice-dollar'],
                    'tooltip' => __('Invoices'),
                ],
            ],
        ];
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
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug,
                    ],
                ],
                'number' => $stats->number_current_org_stocks,
            ],

            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.index',
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
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.abnormality_org_stocks.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.abnormality_org_stocks.index',
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
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.',
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index',
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
        $title      = __('SKOs');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-box'],
            'title' => __('SKOs'),
        ];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof OrgStockFamily) {
            $subNavigation = $this->getOrgStockFamilySubNavigation($this->parent, $request);
            $title         = $this->parent->name;
            $icon          = [
                'icon'  => ['fal', 'fa-boxes-alt'],
                'title' => __('SKO Family'),
            ];
            $iconRight     = [
                'icon' => 'fal fa-box',
            ];
            $afterTitle    = [
                'label' => __('SKOs'),
            ];
        } elseif ($this->parent instanceof OrgPartner) {
            $subNavigation = $this->getOrgPartnerNavigation($this->parent);
            $title         = $this->parent->partner->name;
            $icon          = [
                'icon'  => ['fal', 'fa-users-class'],
                'title' => __('SKOs'),
            ];
            $iconRight     = [
                'icon' => 'fal fa-box',
            ];
            $afterTitle    = [
                'label' => __('SKOs'),
            ];
        } elseif ($this->parent instanceof OrgAgent) {
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $title         = $this->parent->agent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('SKOs'),
            ];
            $iconRight     = [
                'icon' => 'fal fa-box',
            ];
            $afterTitle    = [
                'label' => __('SKOs'),
            ];
        } else {
            $subNavigation = $this->getOrgStocksSubNavigation();
        }

        if ($this->bucket == 'current') {
            $title = __('Current SKOs');
        }

        return Inertia::render(
            'Org/Inventory/OrgStocks',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
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
                    : Inertia::optional(fn () => OrgStocksResource::collection($this->handle(parent: $this->parent, prefix: OrgStocksTabsEnum::INDEX->value, bucket: $this->bucket))),

                OrgStocksTabsEnum::SALES->value => $this->tab == OrgStocksTabsEnum::SALES->value
                    ? fn () => OrgStocksResource::collection($stocks)
                    : Inertia::optional(fn () => OrgStocksResource::collection($this->handle(parent: $this->parent, prefix: OrgStocksTabsEnum::SALES->value, bucket: $this->bucket))),
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
                        'label' => 'SKOs',
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    trim('('.__('Current').') '.$suffix)
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.index' => array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    trim('('.__('Discontinued').') '.$suffix)
                )
            ),

            'grp.org.procurement.org_partners.show.org-stocks.index' => array_merge(
                ShowOrgPartner::make()->getBreadcrumbs($this->parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_agents.show.org-stocks.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                )
            ),

            'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index' => array_merge(
                ShowOrgStockFamily::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }
}
