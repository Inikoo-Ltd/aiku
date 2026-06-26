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

class IndexOrphanTradeUnits extends GrpAction
{
    use WithGoodsAuthorisation;
    use WithTradeUnitIndex;
    use WithTradeUnitStandardIndex;

    private Group $parent;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value);
    }

    public function handle(?string $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->where('trade_units.group_id', $this->group->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');
        $queryBuilder->whereNull('trade_units.trade_unit_family_id');
        $queryBuilder->whereIn('trade_units.status', [TradeUnitStatusEnum::ACTIVE, TradeUnitStatusEnum::IN_PROCESS]);

        return $this->handleStandardTradeUnitIndex($queryBuilder, $prefix);
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
                'title'       => __('Trade Units No Family'),
                'pageHead'    => [
                    'title'         => __('Trade Units No Family'),
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Trade Units No Family'),
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
                    ? fn () => $this->jsonResponse($this->handle(prefix: TradeUnitsTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => $this->jsonResponse($this->handle(prefix: TradeUnitsTabsEnum::SALES->value))),
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
