<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:56:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\TradeUnitFamilyTabsEnum;
use App\Enums\UI\SupplyChain\TradeUnitTabsEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTradeUnitFamily extends GrpAction
{
    use WithGoodsAuthorisation;


    public function handle(TradeUnitFamily $tradeUnitFamily): TradeUnitFamily
    {
        return $tradeUnitFamily;
    }


    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): TradeUnitFamily
    {
        $this->initialisation(group(), $request)->withTab(TradeUnitTabsEnum::values());

        return $this->handle($tradeUnitFamily);
    }

    public function htmlResponse(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/TradeUnitFamily',
            [
                'title'            => __('Trade Unit Family'),
                'breadcrumbs'      => $this->getBreadcrumbs(
                    $tradeUnitFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'       => [
                    'previous' => $this->getPrevious($tradeUnitFamily, $request),
                    'next'     => $this->getNext($tradeUnitFamily, $request),
                ],
                'pageHead'         => [
                    'icon'    => [
                        'title' => __('trade unit family'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'title'   => $tradeUnitFamily->code,
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitFamilyTabsEnum::navigation()

                ],
            ]
        );
    }

    public function getBreadcrumbs(TradeUnitFamily $tradeUnitFamily, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (TradeUnitFamily $tradeUnitFamily, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Trade Unit Families')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $tradeUnitFamily->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.goods.trade-unit-families.index' =>
            array_merge(
                ShowGoodsDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $tradeUnitFamily,
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
            default => []
        };
    }

    public function getPrevious(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): ?array
    {
        $previous = TradeUnit::where('code', '<', $tradeUnitFamily->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): ?array
    {
        $next = TradeUnit::where('code', '>', $tradeUnitFamily->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?TradeUnitFamily $tradeUnitFamily, string $routeName): ?array
    {
        if (!$tradeUnitFamily) {
            return null;
        }


        return match ($routeName) {
            'grp.goods.trade-units.show' => [
                'label' => $tradeUnitFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'tradeUnitFamily' => $tradeUnitFamily->slug
                    ]
                ]
            ],
        };
    }
}
