<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Actions\Goods\TradeUnit\UI\Traits\WithTradeUnitIndex;
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
    use WithTradeUnitIndex;

    private Group $parent;
    private string $bucket;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle();
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
        $globalSearch = $this->tradeUnitGlobalSearch();

        $this->updateQueryBuilderParametersIfPrefixed($prefix);

        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->where('trade_units.group_id', $this->group->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');

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
                'trade_units.marketing_weight',
                'trade_units.marketing_dimensions',
                'trade_units.volume',
                'trade_units.type',
                'trade_unit_stats.number_current_stocks',
                'trade_unit_stats.number_current_products',
                'trade_units.id'
            ]);
        return $this->finalizeTradeUnitIndex(
            queryBuilder: $queryBuilder,
            allowedSorts: ['code', 'type', 'name', 'number_current_stocks', 'number_current_products','marketing_weight'],
            globalSearch: $globalSearch,
            prefix: $prefix
        );
    }

    public function tableStructure(Group $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            $emptyState = match (class_basename($parent)) {
                'Group' => [
                    'title' => __("No Trade Units found"),
                ],
                default => null
            };

            $this->setupTradeUnitTable(
                table: $table,
                modelOperations: $modelOperations,
                prefix: $prefix,
                withLabelRecord: true,
                emptyState: $emptyState
            );

            $this->addColumnCodeAndName($table);
            $this->addColumnType($table, 'Unit label');

            $routeName = request()->route()->getName();
            if (str_starts_with($routeName, 'grp.goods.')) {
                $this->addColumnNumberCurrentStocks($table);
            } else {
                $this->addColumnNumberCurrentProducts($table);
            }

            $this->addColumnMarketingWeight($table);
        };
    }


    public function jsonResponse(LengthAwarePaginator $tradeUnits): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnits);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnits, ActionRequest $request): Response
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
                    'title'         => $title,
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => $title,
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
                        'label' => __('Trade Units'),
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

    public function getTradeUnitsSubNavigation(): array
    {
        return [

            [
                'label'  => __('Active'),
                'root'   => 'grp.trade_units.units.active',
                'route'  => [
                    'name'       => 'grp.trade_units.units.active',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_active
            ],
            [
                'label'  => __('In process'),
                'root'   => 'grp.trade_units.units.in_process',
                'route'  => [
                    'name'       => 'grp.trade_units.units.in_process',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_in_process
            ],
            [
                'label'  => __('Discontinued'),
                'root'   => 'grp.trade_units.units.discontinued',
                'route'  => [
                    'name'       => 'grp.trade_units.units.discontinued',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_discontinued
            ],
            [
                'label'  => __('Anomality'),
                'root'   => 'grp.trade_units.units.anomality',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.trade_units.units.anomality',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_anomality
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.trade_units.units.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.trade_units.units.index',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units

            ],

        ];
    }
}
