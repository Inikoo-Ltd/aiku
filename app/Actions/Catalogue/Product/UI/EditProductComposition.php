<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithMasterAssetTradeUnits;
use App\Actions\Traits\WithUnitsChangeConfirmation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The composition triangle for a shop product: its trade units, how the warehouse packs
 * them, and the price they imply. Only reachable when the product does not follow its
 * master's trade units, because otherwise the master composition page is the truth.
 */
class EditProductComposition extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithMasterAssetTradeUnits;
    use WithUnitsChangeConfirmation;

    public function handle(Product $product): Product
    {
        return $product;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): Product
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/ProductComposition',
            [
                'title'       => __('Composition').': '.$product->code,
                'breadcrumbs' => $this->getBreadcrumbs($product, $request),
                'pageHead'    => [
                    'model'   => __('Composition & packing'),
                    'title'   => $product->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Composition'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
                                'parameters' => [
                                    'organisation' => $product->organisation->slug,
                                    'shop'         => $product->shop->slug,
                                    'product'      => $product->slug,
                                ]
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => $this->getBlueprint($product),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product.update',
                            'parameters' => [
                                'product' => $product->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBlueprint(Product $product): array
    {
        $canEditTradeUnits = !$product->masterProduct || $product->not_follow_master_trade_units;

        $priceContext = [
            'price'    => (float) $product->price,
            'rrp'      => (float) $product->rrp,
            'currency' => $product->currency->code,
            'units'    => (float) $product->units,
        ];

        return [
            [
                'label'  => __('Trade units'),
                'icon'   => 'fa-light fa-atom',
                'fields' => array_filter([
                    'trade_units' => $canEditTradeUnits ? [
                        'label'            => __('Trade units'),
                        'saveConfirmation' => $this->getUnitsChangeConfirmation($product),
                        'priceContext'     => $priceContext,
                        'type'             => 'list-selector-trade-unit',
                        'key_quantity'     => 'quantity',
                        'withQuantity'     => true,
                        'full'             => true,
                        'noSaveButton'     => false,
                        'use_confirm'      => false,
                        'is_dropship'      => $product->shop->type == ShopTypeEnum::DROPSHIPPING,
                        'tabs' => array_values(array_filter([
                            $product->family?->masterProductCategory ? [
                                'label'      => __('To do'),
                                'routeFetch' => [
                                    'name'       => 'grp.json.master-product-category.recommended-trade-units',
                                    'parameters' => [
                                        'masterProductCategory' => $product->family->masterProductCategory->id,
                                    ],
                                ],
                            ] : null,
                            [
                                'label'      => __('All'),
                                'search'     => true,
                                'routeFetch' => [
                                    'name' => 'grp.json.master_product_category.all_trade_units',
                                ],
                            ],
                        ])),
                        'value' => $this->getTradeUnitsWithPackingData($product),
                    ] : null,
                    'units' => $this->getUnitsField($product, $this->getUnitsChangeConfirmation($product)),
                ]),
            ],
        ];
    }

    private function getTradeUnitsWithPackingData(Product $product)
    {
        $packedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $product->tradeUnits->pluck('id'))
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        return $product->tradeUnits->map(function ($tradeUnit) use ($packedIn) {
            $packedQuantity = max(1, (int)($packedIn[$tradeUnit->id] ?? 0));

            return array_merge(
                ['quantity' => (int)$tradeUnit->pivot->quantity],
                ['fraction' => $tradeUnit->pivot->quantity / $packedQuantity],
                ['packed_in' => $packedQuantity],
                ['pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($tradeUnit->pivot->quantity / $packedQuantity)), $packedQuantity)],
                $tradeUnit->toArray()
            );
        });
    }

    public function getBreadcrumbs(Product $product, ActionRequest $request): array
    {
        return ShowProduct::make()->getBreadcrumbs(
            parent: $product->shop,
            product: $product,
            routeName: 'grp.org.shops.show.catalogue.products.all_products.show',
            routeParameters: [
                'organisation' => $product->organisation->slug,
                'shop'         => $product->shop->slug,
                'product'      => $product->slug,
            ],
            suffix: '('.__('Composition').')'
        );
    }
}
