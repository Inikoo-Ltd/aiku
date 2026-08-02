<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:26:52 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStock extends OrgAction
{
    use WithStockNavigation;
    use WithGoodsEditAuthorisation;

    public function handle(Stock $stock): Stock
    {
        return $stock;
    }

    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisationFromGroup($stock->group, $request);

        return $this->handle($stock);
    }

    public function inStockFamily(StockFamily $stockFamily, Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisationFromGroup($stockFamily->group, $request);

        return $this->handle($stock);
    }

    public function htmlResponse(Stock $stock, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('sku'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $stock,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($stock, $request),
                    'next'     => $this->getNext($stock, $request),
                ],
                'pageHead'    => [
                    'title'   => $stock->name,
                    'icon'    => [
                        'title' => __('SKOs'),
                        'icon'  => 'fal fa-box'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $stock->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $stock->name
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Trade units'),
                            'icon'   => 'fa-light fa-atom',
                            'fields' => [
                                'trade_units' => [
                                    'label'       => __('Trade units'),
                                    'type'        => 'trade-units-for-stock',
                                    'fetchRoute'  => [
                                        'name' => 'grp.json.master_product_category.all_trade_units',
                                    ],
                                    'impactRoute' => [
                                        'name'       => 'grp.json.stock.trade-unit-changes-impact',
                                        'parameters' => [
                                            'stock' => $stock->slug
                                        ]
                                    ],
                                    'value'       => $stock->tradeUnits->map(fn ($tradeUnit) => [
                                        'id'       => $tradeUnit->id,
                                        'code'     => $tradeUnit->code,
                                        'name'     => $tradeUnit->name,
                                        'quantity' => $tradeUnit->pivot->quantity,
                                    ])->values(),
                                ],
                            ],
                        ],
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.stock.update',
                            'parameters' => $stock->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Stock $stock, string $routeName, array $routeParameters): array
    {
        return ShowStock::make()->getBreadcrumbs(
            stock: $stock,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }


}
