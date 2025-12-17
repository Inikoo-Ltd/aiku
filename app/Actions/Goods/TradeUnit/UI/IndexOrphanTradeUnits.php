<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
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

class IndexOrphanTradeUnits extends GrpAction
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
                $query->whereStartWith('trade_units.code', $value)
                    ->orWhereAnyWordStartWith('trade_units.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TradeUnit::class);
        $queryBuilder->where('trade_units.group_id', $this->group->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');
        $queryBuilder->whereNull('trade_units.trade_unit_family_id');

        $queryBuilder
            ->defaultSort('trade_units.code')
            ->select([
                'trade_units.code',
                'trade_units.slug',
                'trade_units.name',
                'trade_units.description',
                'trade_units.gross_weight',
                'trade_units.net_weight',
                'trade_units.marketing_dimensions',
                'trade_units.volume',
                'trade_units.type',
                'trade_unit_stats.number_current_stocks',
                'trade_unit_stats.number_current_products',
                'trade_units.id'
            ]);


        return $queryBuilder->allowedSorts(['code', 'type', 'name','number_current_stocks','number_current_products'])
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
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            $routeName = request()->route()->getName();

            if (str_starts_with($routeName, 'grp.goods.')) {
                $table->column(key: 'number_current_stocks', label: __('SKUs'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                $table->column(key: 'number_current_products', label: __('Products'), canBeHidden: false, sortable: true, searchable: true);
            }


            $table->column(key: 'net_weight', label: __('Weight'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function jsonResponse(LengthAwarePaginator $tradeUnits): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnits);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnits, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/TradeUnits',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Orphan Trade Units'),
                'pageHead'    => [
                    'title'         => __('Orphan Trade Units'),
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Orphan Trade Units'),
                    ],
                ],
                'data'        => TradeUnitsResource::collection($tradeUnits),

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
                        'label' => __('Orphan Trade Units'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
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
