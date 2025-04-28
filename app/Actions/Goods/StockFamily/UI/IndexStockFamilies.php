<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\UI;

use App\Actions\Goods\HasGoodsAuthorisation;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\OrgAction;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Http\Resources\Goods\StockFamiliesResource;
use App\InertiaTable\InertiaTable;
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

class IndexStockFamilies extends OrgAction
{
    use HasGoodsAuthorisation;

    private string $bucket;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        if ($request->has('dateInterval') && DateIntervalEnum::tryFrom($request->get('dateInterval'))) {
            $this->dateInterval = DateIntervalEnum::from($request->get('dateInterval'));
        }

        $this->initialisationFromGroup(group(), $request);
        $this->bucket = 'all';

        return $this->handle($this->group);
    }

    public function active(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);
        $this->bucket = 'active';

        return $this->handle($this->group);
    }

    public function inProcess(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);
        $this->bucket = 'in_process';

        return $this->handle($this->group);
    }

    public function discontinuing(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);
        $this->bucket = 'discontinuing';

        return $this->handle($this->group);
    }

    public function discontinued(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);
        $this->bucket = 'discontinued';

        return $this->handle($this->group);
    }

    protected function getElementGroups(Group $group): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    StockFamilyStateEnum::labels(),
                    StockFamilyStateEnum::count($group)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],
        ];
    }

    public function handle(Group $group, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stock_families.code', $value)
                    ->orWhereAnyWordStartWith('stock_families.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(StockFamily::class);
        $queryBuilder->where('stock_families.group_id', $group->id);
        $queryBuilder->leftJoin('stock_family_stats', 'stock_family_stats.stock_family_id', 'stock_families.id');
        $queryBuilder->leftJoin('stock_family_sales_intervals', 'stock_family_sales_intervals.stock_family_id', 'stock_families.id');


        if ($this->bucket == 'active') {
            $queryBuilder->where('stock_families.state', StockFamilyStateEnum::ACTIVE);
        } elseif ($this->bucket == 'discontinuing') {
            $queryBuilder->where('stock_families.state', StockFamilyStateEnum::DISCONTINUING);
        } elseif ($this->bucket == 'discontinued') {
            $queryBuilder->where('stock_families.state', StockFamilyStateEnum::DISCONTINUED);
        } elseif ($this->bucket == 'in_process') {
            $queryBuilder->where('stock_families.state', StockFamilyStateEnum::IN_PROCESS);
        } else {
            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder
            ->defaultSort('code')
            ->select([
                'slug',
                'code',
                'stock_families.id as id',
                'name',
                'number_current_stocks',
                'stock_family_sales_intervals.*',
                'stock_family_sales_intervals.revenue_grp_currency_'.$this->dateInterval->value.' as revenue_grp_currency',

            ])
            ->selectRaw(
                "'".$group->currency->code."' as grp_currency_code"
            )
            ->allowedSorts(['code', 'name', 'number_current_stocks', 'revenue_grp_currency'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $parent, $prefix = null, $bucket = 'all'): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $bucket) {
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
                ->withGlobalSearch()
                ->dateInterval($this->dateInterval)
                ->withEmptyState(
                    [
                        'title'       => __('no stock families'),
                        'description' => $this->canEdit ? __('Get started by creating a new stock family.') : null,
                        'count'       => $parent->goodsStats->number_stocks ?? 0,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new stock family'),
                            'label'   => __('stock family'),
                            'route'   => [
                                'name'       => 'grp.goods.stock-families.create',
                                'parameters' => []
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'code', label: 'code', canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_current_stocks', label: 'SKUs', tooltip: __('Current SKUs'), canBeHidden: false, sortable: true)
                ->column(key: 'revenue_grp_currency', label: __('Revenue'), tooltip: __('Revenue'), sortable: true, align: 'right', isInterval: true)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $stocks): AnonymousResourceCollection
    {
        return StockFamiliesResource::collection($stocks);
    }

    public function getStockFamiliesSubNavigation(): array
    {
        return [

            [
                'label'  => __('Active'),
                'root'   => 'grp.goods.stock-families.active.',
                'route'  => [
                    'name'       => 'grp.goods.stock-families.active.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stock_families_state_active ?? 0
            ],
            [
                'label'  => __('In process'),
                'root'   => 'grp.goods.stock-families.in-process.',
                'route'  => [
                    'name'       => 'grp.goods.stock-families.in-process.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stock_families_state_in_process ?? 0
            ],
            [
                'label'  => __('Discontinuing'),
                'root'   => 'grp.goods.stock-families.discontinuing.',
                'route'  => [
                    'name'       => 'grp.goods.stock-families.discontinuing.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stock_families_state_discontinuing ?? 0
            ],
            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.goods.stock-families.discontinued.',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.goods.stock-families.discontinued.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stock_families_state_discontinued ?? 0
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.goods.stock-families.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.goods.stock-families.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_stock_families ?? 0

            ],

        ];
    }

    public function htmlResponse(LengthAwarePaginator $stockFamily, ActionRequest $request): Response
    {
        $parent = $this->group;

        $subNavigation = $this->getStockFamiliesSubNavigation();

        $title = match ($this->bucket) {
            'active' => __('Active SKU Families'),
            'in_process' => __('In process SKU Families'),
            'discontinuing' => __('Discontinuing SKU Families'),
            'discontinued' => __('Discontinued SKU Families'),
            default => __('SKU Families')
        };

        return Inertia::render(
            'Goods/StockFamilies',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'title' => __("SKUs families"),
                        'icon'  => 'fal fa-boxes-alt'
                    ],
                    'actions'       => [
                        $this->canEdit && $request->route()->getName() == 'grp.goods.stock-families.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new SKU family'),
                            'label'   => __('SKU family'),
                            'route'   => [
                                'name'       => 'grp.goods.stock-families.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                    'subNavigation' => $subNavigation
                ],
                'data'        => StockFamiliesResource::collection($stockFamily),
            ]
        )->table($this->tableStructure(parent: $parent, bucket: $this->bucket));
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.goods.stock-families.index'
                        ],
                        'label' => __("SKUs families"),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
