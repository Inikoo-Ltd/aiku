<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Http\Resources\Goods\StocksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStocks extends OrgAction
{
    use WithGoodsAuthorisation;

    private StockFamily|Group $parent;
    private string $bucket;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function active(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function inProcess(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function discontinuing(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinuing';
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function discontinued(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function inStockFamily(StockFamily $stockFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromGroup(group(), $request);
        $this->parent = $stockFamily;

        return $this->handle(parent: $stockFamily);
    }

    protected function getElementGroups(Group|StockFamily $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    StockStateEnum::labels(),
                    StockStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],
        ];
    }


    public function handle(StockFamily|Group $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stocks.code', $value)
                    ->orWhereStartWith('stock_families.code', $value)
                    ->orWhereAnyWordStartWith('stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Stock::class);
        $queryBuilder->leftJoin('stock_sales_intervals', 'stock_sales_intervals.stock_id', 'stocks.id');

        if ($parent instanceof StockFamily) {
            $queryBuilder->where('stock_family_id', $parent->id);
            $group = $parent->group;
        } else {
            $queryBuilder->where('stocks.group_id', $this->group->id);
            $group = $parent;
        }


        if ($this->bucket == 'active') {
            $queryBuilder->where('stocks.state', StockStateEnum::ACTIVE);
        } elseif ($this->bucket == 'discontinuing') {
            $queryBuilder->where('stocks.state', StockStateEnum::DISCONTINUING);
        } elseif ($this->bucket == 'discontinued') {
            $queryBuilder->where('stocks.state', StockStateEnum::DISCONTINUED);
        } elseif ($this->bucket == 'in_process') {
            $queryBuilder->where('stocks.state', StockStateEnum::IN_PROCESS);
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


        $queryBuilder
            ->defaultSort('stocks.code')
            ->select([
                'stocks.code',
                'stocks.slug',
                'stocks.name',
                'stocks.unit_value',
                'stock_sales_intervals.*',
                'stock_sales_intervals.revenue_grp_currency_'.$this->dateInterval->value.' as revenue_grp_currency',

            ])
            ->selectRaw(
                "'".$group->currency->code."' as grp_currency_code"
            )
            ->leftJoin('stock_stats', 'stock_stats.stock_id', 'stocks.id');

        if ($parent instanceof Group) {
            $queryBuilder->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id');
            $queryBuilder->addSelect([
                'stock_families.slug as family_slug',
                'stock_families.code as family_code',
            ]);
        }


        return $queryBuilder->allowedSorts(['code', 'family_code', 'name', 'revenue_grp_currency'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|StockFamily $parent, ?array $modelOperations = null, $prefix = null, $bucket = 'all'): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $bucket) {
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
                ->withGlobalSearch()
                ->dateInterval($this->dateInterval)
                ->withModelOperations($modelOperations)
                ->withEmptyState($this->getEmptyState($parent))
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'family_code', label: __('family'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'revenue_grp_currency', label: __('Revenue'), tooltip: __('Revenue'), sortable: true, align: 'right', isInterval: true);
        };
    }

    private function getEmptyState(Group|StockFamily $parent): ?array
    {
        return match (class_basename($parent)) {
            'Group' => [
                'title'       => __("No SKUs found"),
                'description' => $this->canEdit && $parent->goodsStats->number_stock_families == 0 ? __('Get started by creating a shop. ✨')
                    : __("In fact, is no even create a SKUs family yet 🤷🏽‍♂️"),
                'count'       => $parent->goodsStats->number_stocks,
                'action'      => $this->canEdit && $parent->goodsStats->number_stock_families == 0 ? [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('new SKUs family'),
                    'label'   => __('SKUs family'),
                    'route'   => [
                        'name'       => 'grp.goods.stock-families.create',
                        'parameters' => []
                    ]
                ] : null
            ],
            'StockFamily' => [
                'title'       => __("No SKUs found"),
                'description' => $this->canEdit ? __('Get started by creating a new SKU. ✨')
                    : null,
                'count'       => $parent->stats->number_stocks,
                'action'      => $this->canEdit ? [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('new SKU'),
                    'label'   => __('SKU'),
                    'route'   => [
                        'name'       => 'inventory.stock-families.show.stocks.create',
                        'parameters' => [$parent->slug]
                    ]
                ] : null
            ],
            default => null
        };
    }


    public function jsonResponse(LengthAwarePaginator $stocks): AnonymousResourceCollection
    {
        return StocksResource::collection($stocks);
    }


    public function getStocksSubNavigation(): array
    {
        return [

            [
                'label'  => __('Active'),
                'root'   => 'grp.goods.stocks.active_stocks.',
                'route'  => [
                    'name'       => 'grp.goods.stocks.active_stocks.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stocks_state_active
            ],
            [
                'label'  => __('In process'),
                'root'   => 'grp.goods.stocks.in_process_stocks.',
                'route'  => [
                    'name'       => 'grp.goods.stocks.in_process_stocks.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stocks_state_in_process
            ],
            [
                'label'  => __('Discontinuing'),
                'root'   => 'grp.goods.stocks.discontinuing_stocks.',
                'route'  => [
                    'name'       => 'grp.goods.stocks.discontinuing_stocks.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stocks_state_discontinuing
            ],
            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.goods.stocks.discontinued_stocks.',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.goods.stocks.discontinued_stocks.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stocks_state_discontinued
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.goods.stocks.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.goods.stocks.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stocks

            ],

        ];
    }


    public function htmlResponse(LengthAwarePaginator $stocks, ActionRequest $request): Response
    {
        $subNavigation = $this->getStocksSubNavigation();

        $title = match ($this->bucket) {
            'active' => __('Active SKUs'),
            'in_process' => __('In process SKUs'),
            'discontinuing' => __('Discontinuing SKUs'),
            'discontinued' => __('Discontinued SKUs'),
            default => __('SKUs')
        };

        return Inertia::render(
            'Goods/Stocks',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('SKU')
                    ],
                    'actions'       => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new SKU'),
                            'label'   => __('SKU'),
                            'route'   => match ($request->route()->getName()) {
                                'inventory.stock-families.show.stocks.index' => [
                                    'name'       => 'inventory.stock-families.show.stocks.create',
                                    'parameters' => array_values($request->route()->originalParameters())
                                ],
                                default => [
                                    'name'       => 'grp.goods.stocks.create',
                                    'parameters' => array_values($request->route()->originalParameters())
                                ]
                            }
                        ] : false,
                    ],
                    'subNavigation' => $subNavigation
                ],
                'data'        => StocksResource::collection($stocks),

            ]
        )->table($this->tableStructure(parent: $this->parent, bucket: $this->bucket));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            $label = match ($routeParameters['name']) {
                'grp.goods.stocks.active_stocks.index' => __('Active SKUs'),
                'grp.goods.stocks.in_process_stocks.index' => __('In process SKUs'),
                'grp.goods.stocks.discontinuing_stocks.index' => __('Discontinuing SKUs'),
                'grp.goods.stocks.discontinued_stocks.index' => __('Discontinued SKUs'),
                default => __('SKUs')
            };


            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $label,
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.goods.stocks.index',
            'grp.goods.stocks.active_stocks.index',
            'grp.goods.stocks.in_process_stocks.index',
            'grp.goods.stocks.discontinuing_stocks.index',
            'grp.goods.stocks.discontinued_stocks.index'
            =>
            array_merge(
                ShowGoodsDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => []
                    ],
                    $suffix
                )
            ),

            'grp.goods.stock-families.show.stocks.index' =>
            array_merge(
                ShowStockFamily::make()->getBreadcrumbs($routeParameters),
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
