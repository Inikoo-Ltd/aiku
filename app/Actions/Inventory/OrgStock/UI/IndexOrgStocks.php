<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 15:27:25 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Inventory\HasInventoryAuthorisation;
use App\Actions\Inventory\OrgStockFamily\UI\IndexOrgStockFamilies;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SupplyChain\Stock\StockStateEnum;
use App\Enums\UI\SupplyChain\OrgStocksTabsEnum;
use App\Http\Resources\Inventory\OrgStockFamiliesResource;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStocks extends OrgAction
{
    use HasInventoryAuthorisation;
    use WithOrgPartnerSubNavigation;
    use WithOrgAgentSubNavigation;

    private OrgStockFamily|Organisation|OrgPartner|OrgAgent $parent;
    private string $bucket;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());
        $this->parent = $organisation;

        return $this->handle(parent: $organisation);
    }

    public function maya(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->maya   = true;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());
        $this->parent = $organisation;

        return $this->handle(parent: $organisation);
    }


    public function current(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'current';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent);
    }

    public function active(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->parent = $organisation;
        $this->initialisation($this->parent, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent);
    }

    public function inProcess(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent);
    }

    public function discontinuing(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinuing';
        $this->parent = $organisation;

        $this->initialisation($this->parent, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent);
    }

    public function discontinued(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->parent = $organisation;

        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());

        return $this->handle($this->parent);
    }

    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStocksTabsEnum::values());
        $this->parent = $orgStockFamily;

        return $this->handle(parent: $orgStockFamily);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisation($organisation, $request)->withTab(OrgStocksTabsEnum::values());
        $this->parent = $orgAgent;

        return $this->handle(parent: $orgAgent);
    }

    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisation($organisation, $request)->withTab(OrgStocksTabsEnum::values());
        $this->parent = $orgPartner;

        return $this->handle(parent: $orgPartner);
    }

    protected function getElementGroups(Organisation|OrgStockFamily|OrgPartner|OrgAgent $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OrgStockStateEnum::labels(),
                    OrgStockStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],
        ];
    }


    public function handle(OrgStockFamily|Organisation|OrgAgent|OrgPartner $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }
        // dd($this->bucket);

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
        } elseif ($parent instanceof OrgAgent) {
            $queryBuilder->where('org_stocks.organisation_id', $parent->agent->organisation->id);
        } elseif ($parent instanceof OrgPartner) {
            $queryBuilder->where('org_stocks.organisation_id', $parent->partner->id);
        } else {
            $queryBuilder->where('org_stocks.organisation_id', $this->organisation->id);
        }


        if ($this->bucket == 'current') {
            $queryBuilder->whereIn('org_stocks.state', [StockStateEnum::ACTIVE, StockStateEnum::DISCONTINUING]);
        } elseif ($this->bucket == 'active') {
            $queryBuilder->where('org_stocks.state', StockStateEnum::ACTIVE);
        } elseif ($this->bucket == 'discontinuing') {
            $queryBuilder->where('org_stocks.state', StockStateEnum::DISCONTINUING);
        } elseif ($this->bucket == 'discontinued') {
            $queryBuilder->where('org_stocks.state', StockStateEnum::DISCONTINUED);
        } elseif ($this->bucket == 'in_process') {
            $queryBuilder->where('org_stocks.state', StockStateEnum::IN_PROCESS);
        } else {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder
            ->defaultSort('org_stocks.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.slug',
                'org_stocks.unit_value',
                'number_locations',
                'quantity_in_locations',
                'org_stock_families.slug as family_slug',
                'org_stock_families.code as family_code',
            ])
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoin('org_stock_families', 'org_stocks.org_stock_family_id', 'org_stock_families.id')
            ->allowedSorts(['code', 'family_code', 'unit_value'])
            ->allowedFilters([$globalSearch,AllowedFilter::exact('state')])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(OrgStockFamily|Organisation|OrgPartner|OrgAgent $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'code', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Organisation) {
                $table->column(key: 'family_code', label: __('family'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unit_value', label: __('unit value'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'stock', label: __('stock'), canBeHidden: false, sortable: true, searchable: true);
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
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.',
                'route'   => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $stats->number_current_org_stocks
            ],


            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.',
                'route'   => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'align'  => 'right',
                'number' => $stats->number_org_stocks_state_discontinued
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.',
                'route'   => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $stats->number_org_stocks,
                'align'  => 'right'
            ],

        ];
    }

    public function htmlResponse(LengthAwarePaginator $stocks, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title = __('SKUs');
        $model = '';
        $icon  = [
            'icon'  => ['fal', 'fa-box'],
            'title' => __('SKUs')
        ];
        $afterTitle = null;
        $iconRight = null;

        if ($this->parent instanceof OrgPartner) {
            $subNavigation = $this->getOrgPartnerNavigation($this->parent);
            $organisation = $this->parent->partner;
            $title = $this->parent->partner->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-users-class'],
                'title' => __('SKUs')
            ];
            $iconRight    = [
                'icon' => 'fal fa-box',
            ];
            $afterTitle = [

                'label'     => __('SKUs')
            ];
        } elseif ($this->parent instanceof OrgAgent) {
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $organisation = $this->parent->agent->organisation;
            $title = $this->parent->agent->organisation->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('SKUs')
            ];
            $iconRight    = [
                'icon' => 'fal fa-box',
            ];
            $afterTitle = [

                'label'     => __('SKUs')
            ];
        } else {
            $subNavigation = $this->getOrgStocksSubNavigation();
            $organisation = $this->parent;
        }

        if ($this->bucket == 'current') {
            $title = __('Current SKUs');
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
                    'actions_xx'    => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new SKU'),
                            'label'   => __('SKU'),
                            'route'   => match ($request->route()->getName()) {
                                'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index' => [
                                    'name'       => 'inventory.stock-families.show.stocks.create',
                                    'parameters' => array_values($request->route()->originalParameters())
                                ],
                                default => [
                                    'name'       => 'inventory.stocks.create',
                                    'parameters' => array_values($request->route()->originalParameters())
                                ]
                            }
                        ] : false,
                    ],
                    'subNavigation' => $subNavigation
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgStocksTabsEnum::navigation()
                ],
                'data'        => OrgStocksResource::collection($stocks),

                OrgStocksTabsEnum::ORG_STOCKS->value => $this->tab == OrgStocksTabsEnum::ORG_STOCKS->value ?
                fn () => OrgStocksResource::collection($stocks)
                : Inertia::lazy(fn () => OrgStocksResource::collection($stocks)),

                OrgStocksTabsEnum::ORG_STOCK_FAMILIES->value => $this->tab == OrgStocksTabsEnum::ORG_STOCK_FAMILIES->value ?
                fn () => OrgStockFamiliesResource::collection(IndexOrgStockFamilies::run($organisation))
                : Inertia::lazy(fn () => OrgStockFamiliesResource::collection(IndexOrgStockFamilies::run($organisation))),



            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: OrgStocksTabsEnum::ORG_STOCKS->value))
        ->table(IndexOrgStockFamilies::make()->tableStructure(organisation: $organisation, prefix: OrgStocksTabsEnum::ORG_STOCK_FAMILIES->value));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('SKUs'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index' =>
            array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index' =>
            array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Current').') '.$suffix)
                )
            ),
            'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.index' =>
            array_merge(
                ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    trim('('.__('Discontinued').') '.$suffix)
                )
            ),

            'inventory.stock-families.show.stocks.index' =>
            array_merge(
                ShowStockFamily::make()->getBreadcrumbs($routeParameters['stockFamily']),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_partners.show.org-stocks.index' =>
            array_merge(
                ShowOrgPartner::make()->getBreadcrumbs($this->parent, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_agents.show.org-stocks.index' =>
            array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }
}
