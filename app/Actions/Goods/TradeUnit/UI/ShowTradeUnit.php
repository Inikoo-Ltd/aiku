<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:56:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocksInTradeUnit;
use App\Actions\Masters\MasterAsset\UI\IndexMasterProductsInTradeUnit;
use App\Actions\Catalogue\Product\UI\IndexProductsInTradeUnit;
use App\Actions\Goods\Stock\UI\IndexStocksInTradeUnit;
use App\Actions\Goods\TradeUnit\IndexTradeUnitImages;
use App\Actions\OrgAction;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\TradeUnitTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Http\Resources\Goods\StocksResource;
use App\Http\Resources\Goods\TradeUnitResource;
use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Goods\TradeUnit;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTradeUnit extends OrgAction
{
    use WithBucketNavigation;

    use WithGoodsAuthorisation;


    public function handle(TradeUnit $tradeUnit): TradeUnit
    {
        return $tradeUnit;
    }


    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->initialisationFromGroup(group(), $request)->withTab(TradeUnitTabsEnum::values());

        return $this->handle($tradeUnit);
    }

    public function htmlResponse(TradeUnit $tradeUnit, ActionRequest $request): Response
    {
        $miniBreadcrumbs = [];
        if ($tradeUnit->tradeUnitFamily) {
            $miniBreadcrumbs[] = [
                'label'   => $tradeUnit->tradeUnitFamily->code,
                'to'      => [
                    'name'       => 'grp.trade_units.families.show',
                    'parameters' => [
                        'tradeUnitFamily' => $tradeUnit->tradeUnitFamily->slug
                    ]
                ],
                'tooltip' => __('Trade Unit Family'),
                'icon'    => ['fal', 'fa-atom-alt']
            ];
        }
        
        $miniBreadcrumbs[] = [
            'label'   => $tradeUnit->code,
            'to'      => null,
            'tooltip' => __('Trade Unit'),
            'icon'    => ['fal', 'fa-atom']
        ];

        return Inertia::render(
            'Goods/TradeUnit',
            [
                'title'       => __('Trade Unit').' '.$tradeUnit->code,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $tradeUnit,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($tradeUnit, $request),
                    'up'       => $this->getParent($tradeUnit),
                    'next'     => $this->getNext($tradeUnit, $request),
                ],
                'mini_breadcrumbs'  => $miniBreadcrumbs,
                'pageHead'    => [
                    'icon'       => [
                        'title' => __('Trade unit'),
                        'icon'  => 'fal fa-atom'
                    ],
                    'model'      => __('Trade unit'),
                    'title'      => $tradeUnit->code,
                    'afterTitle' => [
                        'label' => $tradeUnit->status->labels()[$tradeUnit->status->value]
                    ],
                    'actions'    => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitTabsEnum::navigation()
                ],
                'tradeUnitFamilySlug'   => $tradeUnit->tradeUnitFamily?->slug,
                TradeUnitTabsEnum::SHOWCASE->value => $this->tab == TradeUnitTabsEnum::SHOWCASE->value ?
                    fn () => GetTradeUnitShowcase::run($tradeUnit)
                    : Inertia::optional(fn () => GetTradeUnitShowcase::run($tradeUnit)),

                TradeUnitTabsEnum::COMPOSITION->value => $this->tab == TradeUnitTabsEnum::COMPOSITION->value ?
                    fn () => GetTradeUnitComposition::run($tradeUnit)
                    : Inertia::optional(fn () => GetTradeUnitComposition::run($tradeUnit)),

                TradeUnitTabsEnum::ATTACHMENTS->value => $this->tab == TradeUnitTabsEnum::ATTACHMENTS->value ?
                    fn () => GetTradeUnitAttachment::run($tradeUnit)
                    : Inertia::optional(fn () => GetTradeUnitAttachment::run($tradeUnit)),

                TradeUnitTabsEnum::IMAGES->value => $this->tab == TradeUnitTabsEnum::IMAGES->value ?
                    fn () => GetTradeUnitImages::run($tradeUnit)
                    : Inertia::optional(fn () => GetTradeUnitImages::run($tradeUnit)),

                TradeUnitTabsEnum::MASTER_PRODUCTS->value => $this->tab == TradeUnitTabsEnum::MASTER_PRODUCTS->value ?
                    fn () => MasterProductsResource::collection(IndexMasterProductsInTradeUnit::run($tradeUnit))
                    : Inertia::optional(fn () => MasterProductsResource::collection(IndexMasterProductsInTradeUnit::run($tradeUnit))),

                TradeUnitTabsEnum::PRODUCTS->value => $this->tab == TradeUnitTabsEnum::PRODUCTS->value ?
                    fn () => ProductsResource::collection(IndexProductsInTradeUnit::run($tradeUnit))
                    : Inertia::optional(fn () => ProductsResource::collection(IndexProductsInTradeUnit::run($tradeUnit))),

                TradeUnitTabsEnum::STOCKS->value => $this->tab == TradeUnitTabsEnum::STOCKS->value ?
                    fn () => StocksResource::collection(IndexStocksInTradeUnit::run($tradeUnit))
                    : Inertia::optional(fn () => StocksResource::collection(IndexStocksInTradeUnit::run($tradeUnit))),

                TradeUnitTabsEnum::ORG_STOCKS->value => $this->tab == TradeUnitTabsEnum::ORG_STOCKS->value ?
                    fn () => OrgStocksResource::collection(IndexOrgStocksInTradeUnit::run($tradeUnit))
                    : Inertia::optional(fn () => OrgStocksResource::collection(IndexOrgStocksInTradeUnit::run($tradeUnit))),

            ]
        )
        ->table(IndexMasterProductsInTradeUnit::make()->tableStructure(prefix: TradeUnitTabsEnum::MASTER_PRODUCTS->value))
        ->table(IndexProductsInTradeUnit::make()->tableStructure(prefix: TradeUnitTabsEnum::PRODUCTS->value))
        ->table(IndexStocksInTradeUnit::make()->tableStructure(prefix: TradeUnitTabsEnum::STOCKS->value))
        ->table(IndexOrgStocksInTradeUnit::make()->tableStructure(prefix: TradeUnitTabsEnum::ORG_STOCKS->value))
        ->table(IndexAttachments::make()->tableStructure(TradeUnitTabsEnum::ATTACHMENTS->value))
        ->table(IndexTradeUnitImages::make()->tableStructure($tradeUnit, TradeUnitTabsEnum::IMAGES->value));
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
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.trade_units.units.show' =>
            array_merge(
                ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
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
        return $this->getNavigation($this->getTradeUnitNeighbour($tradeUnit, $request, forward: false), $request->route()->getName());
    }

    public function getNext(TradeUnit $tradeUnit, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getTradeUnitNeighbour($tradeUnit, $request, forward: true), $request->route()->getName());
    }

    public function getParent(TradeUnit $tradeUnit): ?array
    {
        if (!$tradeUnit->tradeUnitFamily) {
            return null;
        }
        
        return [
            'label' => $tradeUnit->tradeUnitFamily->name,
            'route' => [
                'name'       => 'grp.trade_units.families.show',
                'parameters' => [
                    'tradeUnitFamily' => $tradeUnit->tradeUnitFamily->slug
                ]
            ]
        ];
    }

    private function getTradeUnitNeighbour(TradeUnit $tradeUnit, ActionRequest $request, bool $forward): ?TradeUnit
    {
        $query = TradeUnit::query()->where('trade_units.group_id', $tradeUnit->group_id);

        $status = match ($request->input('bucket')) {
            'in_process'    => TradeUnitStatusEnum::IN_PROCESS,
            'active'        => TradeUnitStatusEnum::ACTIVE,
            'discontinuing' => TradeUnitStatusEnum::DISCONTINUING,
            'discontinued'  => TradeUnitStatusEnum::DISCONTINUED,
            'anomality'     => TradeUnitStatusEnum::ANOMALITY,
            default         => null,
        };

        if ($status) {
            $query->where('trade_units.status', $status);
        }

        return $this->getBucketNeighbour(
            query: $query,
            model: $tradeUnit,
            sort: $request->input('bucket_sort'),
            sortColumns: [
                'code' => 'trade_units.code',
                'name' => 'trade_units.name',
            ],
            defaultSort: ['trade_units.code', false],
            forward: $forward
        );
    }

    private function getNavigation(?TradeUnit $tradeUnit, string $routeName): ?array
    {
        if (!$tradeUnit) {
            return null;
        }

        return [
            'label' => $tradeUnit->name,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'tradeUnit' => $tradeUnit->slug
                ]
            ]
        ];
    }
}
