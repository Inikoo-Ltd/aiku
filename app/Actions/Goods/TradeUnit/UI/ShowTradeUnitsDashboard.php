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
                        'icon'  => ['fal', 'fa-cloud-atom'],
                        'title' => $title
                    ],
                    'title' => __('Trade Units Dashboard'),
                ],
                'flatTreeMaps' => [
                    [
                        [
                            'name'  => __('Trade Units'),
                            'icon'  => ['fal', 'fa-atom'],
                            'route' => [
                                'name'       => 'grp.trade_units.units.active',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->goodsStats->number_trade_units
                            ]

                        ],
                        [
                            'name'  => __('Trade Unit Families'),
                            'icon'  => ['fal', 'fa-atom'],
                            'route' => [
                                'name'       => 'grp.trade_units.families.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->goodsStats->number_trade_unit_families
                            ]
                        ],
                        [
                            'name'  => __('Orphan Trade Units'),
                            'icon'  => ['fal', 'fa-atom'],
                            'route' => [
                                'name'       => 'grp.trade_units.families.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->goodsStats->number_trade_unit_families
                            ]

                        ]
                    ]
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
                            'label' => __('Trade Units'),
                        ]
                    ]
                ]
            );
    }


}
