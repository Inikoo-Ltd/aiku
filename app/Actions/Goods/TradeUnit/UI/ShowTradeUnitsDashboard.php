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
                            'name'       => 'grp.trade_units.units.active',
                            'parameters' => []
                        ],
                        'color' => '#35858E',
                        'icon'  => 'fal fa-atom',
                        'value' => $this->group->goodsStats->number_trade_units,
                    ],
                    [
                        'label' => __('Trade Unit Families'),
                        'route' => [
                            'name'       => 'grp.trade_units.families.index',
                            'parameters' => []
                        ],
                        'color' => '#7DA78C',
                        'icon'  => 'fal fa-atom',
                        'value' => $this->group->goodsStats->number_trade_unit_families,
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
                        'label'           => __('Without Marketing Weight'),
                        'route'           => [
                            'name'       => 'grp.trade_units.units.missing_weight',
                            'parameters' => []
                        ],
                        'icon'            => 'fal fa-weight',
                        'backgroundColor' => '#ff000011',
                        'color'           => '#df1c1cff',
                        'value'           => $this->group->goodsStats->number_trade_units_without_marketing_weight,
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
