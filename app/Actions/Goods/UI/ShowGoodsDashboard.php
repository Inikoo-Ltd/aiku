<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:14:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGoodsDashboard extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("goods.{$this->group->id}.view");
    }


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
                            'name'  => __('SKUs families'),
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
                            'name'  => 'SKUs',
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
