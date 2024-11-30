<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 04:21:44 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Goods;

use App\Actions\GrpAction;
use App\Actions\UI\Grp\Dashboard\ShowDashboard;
use App\Actions\UI\WithInertia;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGoodsDashboard extends GrpAction
{
    use AsAction;
    use WithInertia;


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("goods.{$this->group->id}.view");
    }


    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation(app('group'), $request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Goods/GoodsDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => __('goods'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-cloud-rainbow'],
                        'title' => __('goods')
                    ],
                    'title' => __('goods strategy'),
                ],
                'flatTreeMaps' => [
                    [
                        [
                            'name'  => __('SKUs families'),
                            'icon'  => ['fal', 'fa-boxes-alt'],
                            'route'  => [
                                'name'       => 'grp.goods.stock-families.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->inventoryStats->number_stock_families
                            ]

                        ],
                        [
                            'name'  => 'SKUs',
                            'icon'  => ['fal', 'fa-box'],
                            'route'  => [
                                'name'       => 'grp.goods.stocks.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->inventoryStats->number_stocks
                            ]

                        ],
                        [
                            'name'  => 'Catalogue',
                            'icon'  => ['fal', 'fa-books'],
                            'route'  => [
                                'name'       => 'grp.goods.catalogue.shops.index',
                                'parameters' => []
                            ],
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
                ShowDashboard::make()->getBreadcrumbs(),
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
