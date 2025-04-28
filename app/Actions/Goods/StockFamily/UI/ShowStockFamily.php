<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\UI;

use App\Actions\Goods\Stock\UI\IndexStocks;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\StockFamilyTabsEnum;
use App\Http\Resources\Goods\StockFamilyResource;
use App\Http\Resources\Goods\StocksResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Goods\StockFamily;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStockFamily extends OrgAction
{
    use WithGoodsAuthorisation;

    public function handle(StockFamily $stockFamily): StockFamily
    {
        return $stockFamily;
    }

    public function asController(StockFamily $stockFamily, ActionRequest $request): StockFamily
    {
        $this->initialisationFromGroup(group(), $request)->withTab(StockFamilyTabsEnum::values());

        return $this->handle($stockFamily);
    }

    public function htmlResponse(StockFamily $stockFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/StockFamily',
            [
                'title'       => __('stock family'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($stockFamily, $request),
                    'next'     => $this->getNext($stockFamily, $request),
                ],
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-boxes-alt'],
                            'title' => __('stock family')
                        ],
                    'title'   => $stockFamily->name,
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                ],
                'createStockRoute' => [
                    'name'       => 'grp.goods.stock-families.show.stocks.create',
                    'parameters' => [
                        'stockFamily' => $stockFamily->slug
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => StockFamilyTabsEnum::navigation()

                ],

                StockFamilyTabsEnum::SHOWCASE->value => $this->tab == StockFamilyTabsEnum::SHOWCASE->value ?
                    fn () => GetStockFamilyShowcase::run($stockFamily)
                    : Inertia::lazy(fn () => GetStockFamilyShowcase::run($stockFamily)),
                StockFamilyTabsEnum::STOCKS->value    => $this->tab == StockFamilyTabsEnum::STOCKS->value
                    ?
                    fn () => StocksResource::collection(
                        IndexStocks::run(
                            parent: $stockFamily,
                            prefix: StockFamilyTabsEnum::STOCKS->value,
                            bucket: 'all'
                        )
                    )
                    : Inertia::lazy(fn () => StocksResource::collection(
                        IndexStocks::run(
                            parent: $stockFamily,
                            prefix: StockFamilyTabsEnum::STOCKS->value,
                            bucket: 'all'
                        )
                    )),
                StockFamilyTabsEnum::HISTORY->value  => $this->tab == StockFamilyTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($stockFamily))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($stockFamily)))
            ]
        ) ->table(
            IndexStocks::make()->tableStructure(
                parent: $stockFamily,
                prefix: StockFamilyTabsEnum::STOCKS->value,
            )
        );
    }


    public function jsonResponse(StockFamily $stockFamily): StockFamilyResource
    {
        return new StockFamilyResource($stockFamily);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $stockFamily = StockFamily::where('slug', $routeParameters['stockFamily'])->firstOrFail();

        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.goods.stock-families.index',
                            ],
                            'label' => __('SKUs families'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.goods.stock-families.show',
                                'parameters' => [$stockFamily->slug]
                            ],
                            'label' => $stockFamily->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(StockFamily $stockFamily, ActionRequest $request): ?array
    {
        $previous = StockFamily::where('code', '<', $stockFamily->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(StockFamily $stockFamily, ActionRequest $request): ?array
    {
        $next = StockFamily::where('code', '>', $stockFamily->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?StockFamily $stockFamily, string $routeName): ?array
    {
        if (!$stockFamily) {
            return null;
        }

        return match ($routeName) {
            'grp.goods.stock-families.show' => [
                'label' => $stockFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stockFamily' => $stockFamily->slug
                    ]

                ]
            ]
        };
    }
}
