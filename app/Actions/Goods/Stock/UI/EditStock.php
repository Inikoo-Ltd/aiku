<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:26:52 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\InertiaAction;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStock extends InertiaAction
{
    public function handle(Stock $stock): Stock
    {
        return $stock;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo('inventory.stocks.edit');
        return $request->user()->hasPermissionTo("inventory.stocks.view");
    }

    public function inOrganisation(Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisation($request);

        return $this->handle($stock);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inStockFamily(StockFamily $stockFamily, Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisation($request);

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
                'navigation'                            => [
                    'previous' => $this->getPrevious($stock, $request),
                    'next'     => $this->getNext($stock, $request),
                ],
                'pageHead' => [
                    'title'    => $stock->name,
                    'icon'     => [
                        'title' => __('skus'),
                        'icon'  => 'fal fa-box'
                    ],
                    'actions'  => [
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
                            'title'  => __('edit sku'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $stock->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $stock->code
                                ],
                            ],
                        ]
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
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(Stock $stock, ActionRequest $request): ?array
    {
        $previous = Stock::where('code', '<', $stock->code)->when(true, function ($query) use ($stock, $request) {
            if ($request->route()->getName() == 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.edit') {
                $query->where('stock_family_id', $stock->stockFamily->id);
            }
        })->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Stock $stock, ActionRequest $request): ?array
    {
        $next = Stock::where('code', '>', $stock->code)->when(true, function ($query) use ($stock, $request) {
            if ($request->route()->getName() == 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.edit') {
                $query->where('stock_family_id', $stock->stockFamily->id);
            }
        })->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Stock $stock, string $routeName): ?array
    {
        if (!$stock) {
            return null;
        }

        return match ($routeName) {
            'grp.goods.stocks.edit' => [
                'label' => $stock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stock' => $stock->slug
                    ]
                ]
            ],
            'grp.org.warehouses.show.inventory.org-stocks.edit' => [
                'label' => $stock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stock' => $stock->slug
                    ]
                ]
            ],
            'grp.goods.stocks.active_stocks.edit' => [
                'label' => $stock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stock' => $stock->slug
                    ]
                ]
            ],
            'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.edit' => [
                'label' => $stock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stockFamily'   => $stock->stockFamily->slug,
                        'stock'         => $stock->slug
                    ]

                ]
            ]
        };
    }
}
