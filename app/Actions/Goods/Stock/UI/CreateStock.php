<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:26:52 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateStock extends OrgAction
{
    use WithGoodsEditAuthorisation;

    private Group|StockFamily $parent;

    public function asController(ActionRequest $request): Response
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $request);
    }

    public function inStockFamily(StockFamily $stockFamily, ActionRequest $request): Response
    {
        $this->parent = $stockFamily;
        $this->initialisationFromGroup($stockFamily->group, $request);

        return $this->handle($stockFamily, $request);
    }

    public function handle(Group|StockFamily $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('new stock'),
                'icon'     =>
                    [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('SKU')
                    ],
                'pageHead' => [
                    'title'        => __('new SKU'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('new SKU'),
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                            ]
                        ]
                    ],
                    'route' => match ($request->route()->getName()) {
                        'grp.goods.stocks.create' => [
                            'name'      => 'grp.models.stock.store',
                            'parameters' => []
                        ],
                        'grp.goods.stock-families.show.stocks.create' => [
                            'name'      => 'grp.models.stock-family.stock.store',
                            'parameters' => [
                                'stockFamily' => $parent->id
                            ]
                        ],
                        default => [
                            'name'      => 'grp.models.stock.store',
                            'arguments' => []
                        ]
                    }
                ],

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexStocks::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating stock'),
                    ]
                ]
            ]
        );
    }
}
