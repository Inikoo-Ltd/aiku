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
use App\Actions\Goods\TradeUnit\UI\Traits\WithTradeUnitStandardIndex;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Enums\UI\Goods\TradeUnitsTabsEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexTradeUnits extends GrpAction
{
    use WithGoodsAuthorisation;
    use WithTradeUnitIndex;
    use WithTradeUnitStandardIndex;

    private Group $parent;
    private string $bucket;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value);
    }

    public function inProcess(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_process';
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value, bucket: 'in_process');
    }

    public function active(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'active';
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value, bucket: 'active');
    }

    public function discontinuing(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinuing';
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value, bucket: 'discontinuing');
    }

    public function discontinued(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'discontinued';
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value, bucket: 'discontinued');
    }

    public function anomality(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'anomality';
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value, bucket: 'anomality');
    }

    public function handle($prefix = null, $bucket = 'all'): LengthAwarePaginator
    {
        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->where('trade_units.group_id', $this->group->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');

        if ($bucket == 'in_process') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::IN_PROCESS);
        } elseif ($bucket == 'active') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::ACTIVE);
        } elseif ($bucket == 'discontinuing') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::DISCONTINUING);
        } elseif ($bucket == 'discontinued') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::DISCONTINUED);
        } elseif ($bucket == 'anomality') {
            $queryBuilder->where('trade_units.status', TradeUnitStatusEnum::ANOMALITY);
        }

        return $this->handleStandardTradeUnitIndex($queryBuilder, $prefix);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnits, ActionRequest $request): Response
    {
        $title = match ($this->bucket) {
            'active' => __('Active Trade Units'),
            'in_process' => __('In process Trade Units'),
            'discontinued'  => __('Discontinued Trade Units'),
            'discontinuing' => __('Discontinuing Trade Units'),
            'anomality'     => __('Anomality Trade Units'),
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
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitsTabsEnum::navigation(),
                ],

                TradeUnitsTabsEnum::INDEX->value => $this->tab == TradeUnitsTabsEnum::INDEX->value
                    ? fn () => $this->jsonResponse($tradeUnits)
                    : Inertia::lazy(fn () => $this->jsonResponse($tradeUnits)),

                TradeUnitsTabsEnum::SALES->value => $this->tab == TradeUnitsTabsEnum::SALES->value
                    ? fn () => $this->jsonResponse($this->handle(prefix: TradeUnitsTabsEnum::SALES->value, bucket: $this->bucket))
                    : Inertia::lazy(fn () => $this->jsonResponse($this->handle(prefix: TradeUnitsTabsEnum::SALES->value, bucket: $this->bucket))),
            ]
        )->table($this->standardTradeUnitTableStructure(parent: $this->parent, prefix: TradeUnitsTabsEnum::INDEX->value))
         ->table($this->standardTradeUnitTableStructure(parent: $this->parent, prefix: TradeUnitsTabsEnum::SALES->value, sales: true));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
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
                'label'  => __('Discontinuing'),
                'root'   => 'grp.trade_units.units.discontinuing',
                'route'  => [
                    'name'       => 'grp.trade_units.units.discontinuing',
                    'parameters' => []
                ],
                'number' => $this->group->goodsStats->number_trade_units_status_discontinuing
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
