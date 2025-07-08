<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:56:01 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Catalogue\Product\UI\IndexProductsInTradeUnit;
use App\Actions\Goods\Stock\UI\IndexStocksInTradeUnit;
use App\Actions\Goods\TradeUnit\IndexTradeUnitImages;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\TradeUnitTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Goods\StocksResource;
use App\Http\Resources\Goods\TradeUnitResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\ImagesResource;
use App\Models\Goods\TradeUnit;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTradeUnit extends GrpAction
{
    use WithGoodsAuthorisation;


    public function handle(TradeUnit $tradeUnit): TradeUnit
    {
        return $tradeUnit;
    }


    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->initialisation(group(), $request)->withTab(TradeUnitTabsEnum::values());

        return $this->handle($tradeUnit);
    }


    public function getImagesData(TradeUnit $tradeUnit): array
    {
        $imagesData = [];

        if ($this->tab == TradeUnitTabsEnum::IMAGES->value) {
            $imagesData = [
                [
                            'label' => __('Main'),
                            'type'  => 'image',
                            'key_in_db' => 'image_id',
                            'images' => $tradeUnit->imageSources(),
                        ],
                        [
                            'label' => __('Video'),
                            'type'  => 'video',
                            'information' => __('You can use YouTube or Vimeo links'),
                            'key_in_db' => 'video_url',
                            'url' => $tradeUnit->video_url,
                        ],
                        [
                            'label' => __('Front side'),
                            'type'  => 'image',
                            'key_in_db' => 'front_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'frontImage'),
                        ],
                        [
                            'label' => __('Left side'),
                            'type'  => 'image',
                            'key_in_db' => 'left_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'leftImage'),
                        ],
                        [
                            'label' => __('3/4 angle side'),
                            'type'  => 'image',
                            'key_in_db' => '34_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'threeQuarterImage'),
                        ],
                        [
                            'label' => __('Right side'),
                            'type'  => 'image',
                            'key_in_db' => 'right_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'rightImage'),
                        ],
                        [
                            'label' => __('Back side'),
                            'type'  => 'image',
                            'key_in_db' => 'back_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'backImage'),
                        ],
                        [
                            'label' => __('Top side'),
                            'type'  => 'image',
                            'key_in_db' => 'top_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'topImage'),
                        ],
                        [
                            'label' => __('Bottom side'),
                            'type'  => 'image',
                            'key_in_db' => 'bottom_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'bottomImage'),
                        ],
                        [
                            'label' => __('Comparison image'),
                            'type'  => 'image',
                            'key_in_db' => 'size_comparison_image_id',
                            'images' => $tradeUnit->imageSources(getImage:'sizeComparisonImage'),
                        ],
            ];
        }

        return $imagesData;
    }

    public function htmlResponse(TradeUnit $tradeUnit, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/TradeUnit',
            [
                'title'            => __('Trade Unit'),
                'breadcrumbs'      => $this->getBreadcrumbs(
                    $tradeUnit,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'       => [
                    'previous' => $this->getPrevious($tradeUnit, $request),
                    'next'     => $this->getNext($tradeUnit, $request),
                ],
                'pageHead'         => [
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
                    ]
                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.trade-unit.attachment.attach',
                        'parameters' => [
                            'tradeUnit' => $tradeUnit->id,
                        ]
                    ],
                    'detachRoute' => [
                        'name'       => 'grp.models.trade-unit.attachment.detach',
                        'parameters' => [
                            'tradeUnit' => $tradeUnit->id,
                        ],
                        'method'     => 'delete'
                    ]
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitTabsEnum::navigation()

                ],

                'images_category_box' => $this->getImagesData($tradeUnit),
                'images_update_route' => [
                    'name'       => 'grp.models.trade-unit.update_images',
                    'parameters' => [
                        'tradeUnit' => $tradeUnit->id,
                    ],
                    'method'     => 'patch'
                ],

                TradeUnitTabsEnum::SHOWCASE->value => $this->tab == TradeUnitTabsEnum::SHOWCASE->value ?
                    fn () => GetTradeUnitShowcase::run($tradeUnit)
                    : Inertia::lazy(fn () => GetTradeUnitShowcase::run($tradeUnit)),

                TradeUnitTabsEnum::ATTACHMENTS->value => $this->tab == TradeUnitTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))),

                TradeUnitTabsEnum::IMAGES->value => $this->tab == TradeUnitTabsEnum::IMAGES->value ?
                    fn () => ImagesResource::collection(IndexTradeUnitImages::run($tradeUnit))
                    : Inertia::lazy(fn () => ImagesResource::collection(IndexTradeUnitImages::run($tradeUnit))),

                TradeUnitTabsEnum::PRODUCTS->value => $this->tab == TradeUnitTabsEnum::PRODUCTS->value ?
                    fn () => ProductsResource::collection(IndexProductsInTradeUnit::run($tradeUnit))
                    : Inertia::lazy(fn () => ProductsResource::collection(IndexProductsInTradeUnit::run($tradeUnit))),

                TradeUnitTabsEnum::STOCKS->value => $this->tab == TradeUnitTabsEnum::STOCKS->value ?
                    fn () => StocksResource::collection(IndexStocksInTradeUnit::run($tradeUnit))
                    : Inertia::lazy(fn () => StocksResource::collection(IndexStocksInTradeUnit::run($tradeUnit))),

            ]
        )
            ->table(IndexProductsInTradeUnit::make()->tableStructure(prefix: TradeUnitTabsEnum::PRODUCTS->value))
            ->table(IndexStocksInTradeUnit::make()->tableStructure(prefix: TradeUnitTabsEnum::STOCKS->value))
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
