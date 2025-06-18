<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTradeUnits extends GrpAction
{
    use WithGoodsAuthorisation;

    private Group $parent;
    private string $bucket;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle(bucket: 'all');
    }

    public function inProcess(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle(bucket: 'in_process');
    }

    public function active(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle(bucket: 'active');
    }

    public function discontinued(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle(bucket: 'discontinued');
    }

    public function anomality(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'anomality';
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle(bucket: 'anomality');
    }

    public function handle($prefix = null, $bucket = 'all'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trade_units.code', $value)
                    ->orWhereAnyWordStartWith('trade_units.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TradeUnit::class);
        $queryBuilder->where('trade_units.group_id', $this->group->id);

        if ($bucket == 'in_process') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::IN_PROCESS);
        } elseif ($bucket == 'active') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::ACTIVE);
        } elseif ($bucket == 'discontinued') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::DISCONTINUED);
        } elseif ($bucket == 'anomality') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::ANOMALITY);
        }

        $queryBuilder
            ->defaultSort('trade_units.code')
            ->select([
                'trade_units.code',
                'trade_units.slug',
                'trade_units.name',
                'trade_units.description',
                'trade_units.gross_weight',
                'trade_units.net_weight',
                'trade_units.dimensions',
                'trade_units.volume',
                'trade_units.type'
            ]);


        return $queryBuilder->allowedSorts(['code', 'type', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Group' => [
                            'title' => __("No Trade Units found"),
                        ],
                        default => null
                    }
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'net_weight', label: __('weight'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function jsonResponse(LengthAwarePaginator $tradeUnit): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnit);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnit, ActionRequest $request): Response
    {
        $title = match ($this->bucket) {
            'active' => __('Active Trade Units'),
            'in_process' => __('In process Trade Units'),
            'discontinued' => __('Discontinued Trade Units'),
            'anomality' => __('Anomality Trade Units'),
            default => __('Trade Units')
        };
        return Inertia::render(
            'Goods/TradeUnits',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'subNavigation' => $this->getTradeUnitsSubNavigation(),
                    'title'     => $title,
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => $title,
                    ],
                ],
                'data'        => TradeUnitsResource::collection($tradeUnit),

            ]
        )->table($this->tableStructure(parent: $this->parent));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Trade Units'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                $suffix
            )
        );
    }

    public function getTradeUnitsSubNavigation(): array
    {
        return [

            [
                'label'  => __('Active'),
                'root'   => 'grp.goods.trade-units.active',
                'route'  => [
                    'name'       => 'grp.goods.trade-units.active',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_active
            ],
            [
                'label'  => __('In process'),
                'root'   => 'grp.goods.trade-units.in_process',
                'route'  => [
                    'name'       => 'grp.goods.trade-units.in_process',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_in_process
            ],
            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.goods.trade-units.discontinued',
                'route'  => [
                    'name'       => 'grp.goods.trade-units.discontinued',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_discontinued
            ],
            [
                'label'  => __('Anomality'),
                'root'   => 'grp.goods.trade-units.anomality',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.goods.trade-units.anomality',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_anomaly
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.goods.trade-units.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.goods.trade-units.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units

            ],

        ];
    }
}
