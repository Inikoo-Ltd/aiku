<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:56:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\TradeUnitTabsEnum;
use App\Http\Resources\Goods\TradeUnitResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTradeUnit extends GrpAction
{
    use WithGoodsAuthorisation;

    private Group $parent;

    public function handle(TradeUnit $tradeUnit): TradeUnit
    {

        return $tradeUnit;
    }


    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitTabsEnum::values());
        return $this->handle($tradeUnit);
    }

    public function htmlResponse(TradeUnit $tradeUnit, ActionRequest $request): Response
    {

        return Inertia::render(
            'Goods/TradeUnit',
            [
                    'title'       => __('Trade Unit'),
                    'breadcrumbs' => $this->getBreadcrumbs(
                        $tradeUnit,
                        $request->route()->getName(),
                        $request->route()->originalParameters()
                    ),
                    'navigation'  => [
                        'previous' => $this->getPrevious($tradeUnit, $request),
                        'next'     => $this->getNext($tradeUnit, $request),
                    ],
                    'pageHead'    => [
                        'icon'    => [
                            'title' => __('trade unit'),
                            'icon'  => 'fal fa-atom'
                        ],
                        'title'   => $tradeUnit->code,
                        'actions' => [
                            $this->canEdit ? [
                                'type'  => 'button',
                                'style' => 'edit',
                                'route' => [
                                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                    'parameters' => array_values($request->route()->originalParameters())
                                ]
                            ] : false,
                            // $this->canDelete ? [
                            //     'type'  => 'button',
                            //     'style' => 'delete',
                            //     'route' => [
                            //         'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.remove',
                            //         'parameters' => array_values($request->route()->originalParameters())
                            //     ]

                            // ] : false
                        ]
                    ],
                    'attachmentRoutes' => [
                        'attachRoute' => [
                            'name' => 'grp.models.trade-unit.attachment.attach',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ]
                        ],
                        'detachRoute' => [
                            'name' => 'grp.models.trade-unit.attachment.detach',
                            'parameters' => [
                                'tradeUnit' => $tradeUnit->id,
                            ],
                            'method'    => 'delete'
                        ]
                    ],

                    'tabs' => [
                        'current'    => $this->tab,
                        'navigation' => TradeUnitTabsEnum::navigation()

                    ],

                    TradeUnitTabsEnum::SHOWCASE->value => $this->tab == TradeUnitTabsEnum::SHOWCASE->value ?
                    fn () => GetTradeUnitShowcase::run($tradeUnit)
                    : Inertia::lazy(fn () => GetTradeUnitShowcase::run($tradeUnit)),

                    TradeUnitTabsEnum::ATTACHMENTS->value => $this->tab == TradeUnitTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))),

            ]
        )->table(IndexAttachments::make()->tableStructure(TradeUnitTabsEnum::ATTACHMENTS->value));
    }


    public function jsonResponse(TradeUnit $tradeUnit): TradeUnitResource
    {
        return new TradeUnitResource($tradeUnit);
    }

    public function getBreadcrumbs(TradeUnit $tradeUnit, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (TradeUnit $tradeUnit, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Trade Units')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $tradeUnit->slug,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.goods.trade-units.show' =>
            array_merge(
                ShowGoodsDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $tradeUnit,
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

    public function getPrevious(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        $previous = TradeUnit::where('code', '<', $tradeUnit->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        $next = TradeUnit::where('code', '>', $tradeUnit->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?TradeUnit $tradeUnit, string $routeName): ?array
    {
        if (!$tradeUnit) {
            return null;
        }


        return match ($routeName) {
            'grp.goods.trade-units.show' => [
                'label' => $tradeUnit->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'tradeUnit' => $tradeUnit->slug
                    ]
                ]
            ],
        };
    }
}
