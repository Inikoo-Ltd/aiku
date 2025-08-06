<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:14:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGoodsDashboard extends OrgAction
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
                        'icon'  => ['fal', 'fa-cloud-rainbow'],
                        'title' => $title
                    ],
                    'title' => __('goods strategy'),
                ],
                'flatTreeMaps' => [
                    [
                        [
                            'name'  => __('Master SKUs families'),
                            'icon'  => ['fal', 'fa-boxes-alt'],
                            'route' => [
                                'name'       => 'grp.goods.stock-families.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->goodsStats->number_stock_families
                            ]

                        ],
                        [
                            'name'  => __('Master SKUs'),
                            'icon'  => ['fal', 'fa-box'],
                            'route' => [
                                'name'       => 'grp.goods.stocks.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->goodsStats->number_stocks
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
                                'name' => 'grp.goods.dashboard'
                            ],
                            'label' => __('Goods'),
                        ]
                    ]
                ]
            );
    }


}
