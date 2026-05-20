<?php

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTradeUnitsDashboard extends OrgAction
{
    use WithGoodsAuthorisation;

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('Goods');

        return Inertia::render(
            'Goods/GoodsDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => $title,
                'pageHead'     => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => $title
                    ],
                    'title' => __('Trade Units Dashboard'),
                ],
                'statsBox' => [
                    [
                        'label' => __('Trade Units'),
                        'route' => [
                            'name'       => 'grp.trade_units.units.index',
                            'parameters' => []
                        ],
                        'color' => '#35858E',
                        'icon'  => 'fal fa-atom',
                        'value' => $this->group->goodsStats->number_trade_units,
                        'metas' => [
                            [
                                'icon'    => ['icon' => 'fas fa-check-circle', 'class' => 'text-green-500'],
                                'count'   => $this->group->goodsStats->number_trade_units_status_active,
                                'tooltip' => __('Active'),
                                'route'   => ['name' => 'grp.trade_units.units.active', 'parameters' => []],
                            ],
                            [
                                'icon'    => ['icon' => 'fal fa-seedling', 'class' => 'text-indigo-500'],
                                'count'   => $this->group->goodsStats->number_trade_units_status_in_process,
                                'tooltip' => __('In Process'),
                                'route'   => ['name' => 'grp.trade_units.units.in_process', 'parameters' => []],
                            ],
                            [
                                'icon'    => ['icon' => 'fal fa-exclamation-triangle', 'class' => 'text-orange-500'],
                                'count'   => $this->group->goodsStats->number_trade_units_status_discontinuing,
                                'tooltip' => __('Discontinuing'),
                                'route'   => ['name' => 'grp.trade_units.units.discontinuing', 'parameters' => []],
                            ],
                            [
                                'icon'    => ['icon' => 'fas fa-skull', 'class' => 'text-yellow-500'],
                                'count'   => $this->group->goodsStats->number_trade_units_status_discontinued,
                                'tooltip' => __('Discontinued'),
                                'route'   => ['name' => 'grp.trade_units.units.discontinued', 'parameters' => []],
                            ],
                            [
                                'icon'    => ['icon' => 'fal fa-scarecrow', 'class' => 'text-slate-500'],
                                'count'   => $this->group->goodsStats->number_trade_units_status_anomality,
                                'tooltip' => __('Anomality'),
                                'route'   => ['name' => 'grp.trade_units.units.anomality', 'parameters' => []],
                            ],
                        ],
                    ],
                    [
                        'label' => __('Trade Unit Families'),
                        'route' => [
                            'name'       => 'grp.trade_units.families.index',
                            'parameters' => []
                        ],
                        'color' => '#7DA78C',
                        'icon'  => 'fal fa-atom',
                        'value' => $this->group->goodsStats->number_stock_families_state_active,
                    ],
                    [
                        'is_negative'     => true,
                        'label'           => __('Trade Units No family'),
                        'route'           => [
                            'name'       => 'grp.trade_units.units.orphan',
                            'parameters' => []
                        ],
                        'icon'            => 'fal fa-atom',
                        'backgroundColor' => '#ff000011',
                        'color'           => '#df1c1cff',
                        'value'           => $this->group->goodsStats->number_orphan_trade_units,
                    ],
                    [
                        'is_negative'     => true,
                        'label'           => __('Without Weight'),
                        'route'           => [
                            'name'       => 'grp.trade_units.units.missing_weight',
                            'parameters' => []
                        ],
                        'icon'            => 'fal fa-weight',
                        'backgroundColor' => '#ff000011',
                        'color'           => '#df1c1cff',
                        'value'           => $this->group->goodsStats->number_trade_units_without_weight,
                    ],
                    [
                        'is_negative'     => true,
                        'label'           => __('Without Marketing Dimensions'),
                        'route'           => [
                            'name'       => 'grp.trade_units.units.missing_dimensions',
                            'parameters' => []
                        ],
                        'icon'            => 'fal fa-ruler-combined',
                        'backgroundColor' => '#ff000011',
                        'color'           => '#df1c1cff',
                        'value'           => $this->group->goodsStats->number_trade_units_without_marketing_dimensions,
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.trade_units.dashboard'
                            ],
                            'label' => __('Trade Units Dashboard'),
                        ]
                    ]
                ]
            );
    }
}
