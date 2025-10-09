<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:56:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Goods\TradeUnit\UI\IndexTradeUnitsInTradeUnitFamily;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\TradeUnitFamilyTabsEnum;
use App\Http\Resources\Goods\TradeUnitsResource;
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
        $this->initialisation(group(), $request)->withTab(TradeUnitFamilyTabsEnum::values());

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
                        'title' => __('Trade unit family'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'title'   => $tradeUnitFamily->code,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'routes' => [
                    'trade_units_route' => [
                        'name' => 'grp.json.trade_unit_family.trade_units',
                        'parameters' => [
                            'tradeUnitFamily' => $tradeUnitFamily->slug, // key must match your route {trade_unit_family}
                        ],
                        'method' => 'get'
                    ],


                    'attach_route' => [
                        'name' => 'grp.models.trade_unit_family.attach_trade_units',
                        'parameters' => [
                            'tradeUnitFamily' => $tradeUnitFamily->id, // key must match your route {trade_unit_family}
                        ],
                        'method' => 'post'
                    ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitFamilyTabsEnum::navigation()

                ],

                TradeUnitFamilyTabsEnum::SHOWCASE->value => $this->tab == TradeUnitFamilyTabsEnum::SHOWCASE->value ?
                fn () => GetTradeUnitFamilyShowcase::run($tradeUnitFamily)
                : Inertia::lazy(fn () => GetTradeUnitFamilyShowcase::run($tradeUnitFamily)),

                TradeUnitFamilyTabsEnum::TRADE_UNITS->value => $this->tab == TradeUnitFamilyTabsEnum::TRADE_UNITS->value ?
                fn () => TradeUnitsResource::collection(IndexTradeUnitsInTradeUnitFamily::run($tradeUnitFamily, TradeUnitFamilyTabsEnum::TRADE_UNITS->value))
                : Inertia::lazy(fn () => TradeUnitsResource::collection(IndexTradeUnitsInTradeUnitFamily::run($tradeUnitFamily, TradeUnitFamilyTabsEnum::TRADE_UNITS->value))),

                TradeUnitFamilyTabsEnum::ATTACHMENTS->value => $this->tab == TradeUnitFamilyTabsEnum::ATTACHMENTS->value ?
                fn () => GetTradeUnitFamilyAttachment::run($tradeUnitFamily)
                : Inertia::lazy(fn () => GetTradeUnitFamilyAttachment::run($tradeUnitFamily)),
            ]
        )->table(IndexTradeUnitsInTradeUnitFamily::make()->tableStructure(prefix: TradeUnitFamilyTabsEnum::TRADE_UNITS->value));
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
            'grp.trade_units.families.show' =>
            array_merge(
                ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
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
        $previous = TradeUnitFamily::where('code', '<', $tradeUnitFamily->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): ?array
    {
        $next = TradeUnitFamily::where('code', '>', $tradeUnitFamily->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?TradeUnitFamily $tradeUnitFamily, string $routeName): ?array
    {
        if (!$tradeUnitFamily) {
            return null;
        }


        return match ($routeName) {
            'grp.trade_units.families.show', => [
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
