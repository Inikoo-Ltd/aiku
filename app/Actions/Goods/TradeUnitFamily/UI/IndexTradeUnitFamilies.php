<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Http\Resources\Goods\TradeUnitFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTradeUnitFamilies extends GrpAction
{
    use WithGoodsAuthorisation;

    private Group $parent;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle();
    }

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trade_unit_families.code', $value)
                    ->orWhereAnyWordStartWith('trade_unit_families.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TradeUnitFamily::class);
        $queryBuilder->where('trade_unit_families.group_id', $this->group->id);
        $queryBuilder->leftjoin('trade_unit_family_stats', 'trade_unit_family_stats.trade_unit_family_id', 'trade_unit_families.id');
        $queryBuilder
            ->defaultSort('trade_unit_families.code')
            ->select([
                'trade_unit_families.code',
                'trade_unit_families.slug',
                'trade_unit_families.name',
                'trade_unit_families.description',
                'trade_unit_families.id',
                'trade_unit_family_stats.number_trade_units'
            ]);


        return $queryBuilder->allowedSorts(['code', 'name'])
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
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function jsonResponse(LengthAwarePaginator $tradeUnitFamilies): AnonymousResourceCollection
    {
        return TradeUnitFamiliesResource::collection($tradeUnitFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnitFamilies, ActionRequest $request): Response
    {
        $actions = [];
        $actions[] = [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('new trade unit family'),
                    'label'   => __('trade unit family'),
                    'route'   => [
                        'name'       => preg_replace('/index$/', 'create', $request->route()->getName()),
                        'parameters' => []
                    ],
                    'method' => 'get'
                ];
        return Inertia::render(
            'Goods/TradeUnitsFamilies',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Trade Unit Families'),
                'pageHead'    => [
                    'title'         => __('Trade Unit Families'),
                     'actions'       => $actions,
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Trade Unit Families'),
                    ],
                ],
                'data'        => TradeUnitFamiliesResource::collection($tradeUnitFamilies),

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
                        'label' => __('Trade Unit Families'),
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
}
