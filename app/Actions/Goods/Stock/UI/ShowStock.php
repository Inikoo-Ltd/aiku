<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:56:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\StockTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStock extends OrgAction
{
    use WithGoodsAuthorisation;
    use WithStockNavigation;

    private Group|StockFamily $parent;

    public function handle(Stock $stock): Stock
    {
        return $stock;
    }


    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request)->withTab(StockTabsEnum::values());

        return $this->handle($stock);
    }

    public function inStockFamily(StockFamily $stockFamily, Stock $stock, ActionRequest $request): Stock
    {
        $this->parent = $stockFamily;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stock);
    }

    public function htmlResponse(Stock $stock, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/Stock',
            [
                'title'                        => __('stock'),
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $stock,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                   => [
                    'previous' => $this->getPrevious($stock, $request),
                    'next'     => $this->getNext($stock, $request),
                ],
                'pageHead'                     => [
                    'icon'    => [
                        'title' => __('skus'),
                        'icon'  => 'fal fa-box'
                    ],
                    'title'   => $stock->slug,
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.remove',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]

                        ] : false
                    ]
                ],
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => StockTabsEnum::navigation()

                ],
                StockTabsEnum::SHOWCASE->value => $this->tab == StockTabsEnum::SHOWCASE->value ?
                    fn () => GetStockShowcase::run($stock)
                    : Inertia::lazy(fn () => GetStockShowcase::run($stock)),

                StockTabsEnum::HISTORY->value => $this->tab == StockTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($stock))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($stock)))


            ]
        )->table();
    }


    public function jsonResponse(Stock $stock): OrgStockResource
    {
        return new OrgStockResource($stock);
    }

    public function getBreadcrumbs(Stock $stock, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Stock $stock, array $routeParameters, $suffix) {
            $label = match ($routeParameters['index']['name']) {
                'grp.goods.stocks.active_stocks.index' => __('Active SKUs'),
                'grp.goods.stocks.in_process_stocks.index' => __('In process SKUs'),
                'grp.goods.stocks.discontinuing_stocks.index' => __('Discontinuing SKUs'),
                'grp.goods.stocks.discontinued_stocks.index' => __('Discontinued SKUs'),
                default => __('SKUs')
            };

            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => $label
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $stock->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.goods.stocks.show',
            'grp.goods.stocks.active_stocks.show',
            'grp.goods.stocks.in_process_stocks.show',
            'grp.goods.stocks.discontinuing_stocks.show',
            'grp.goods.stocks.discontinued_stocks.show'
            =>
            array_merge(
                ShowGoodsDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $stock,
                    [
                        'index' => [
                            'name'       => preg_replace('/show$/', 'index', $routeName),
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.goods.stock_families.show.stocks.show' =>
            array_merge(
                (new ShowStockFamily())->getBreadcrumbs($routeParameters['stockFamily']),
                $headCrumb(
                    $stock,
                    [
                        'index' => [
                            'name'       => 'grp.goods.stock_families.show.stocks.index',
                            'parameters' => Arr::except($routeParameters, 'stock')
                        ],
                        'model' => [
                            'name'       => 'grp.goods.stock_families.show.stocks.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

}
