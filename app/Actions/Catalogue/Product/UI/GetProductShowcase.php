<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:53:47 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Inventory\OrgStock\Json\GetOrgStocksInProduct;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Inventory\OrgStocksResource;

class GetProductShowcase
{
    use AsObject;

    public function handle(Product $product): array
    {
        $tradeUnits = $product->tradeUnits;


        $tradeUnits->loadMissing(['ingredients']);

        $ingredients = $tradeUnits->flatMap(function ($tradeUnit) {
            return $tradeUnit->ingredients->pluck('name');
        })->unique()->values()->all();

        $properties = [
            'country_of_origin' => NaturalLanguage::make()->country($product->country_of_origin),
            'ingredients'       => $ingredients,
            'tariff_code'       => $product->tariff_code,
            'duty_rate'         => $product->duty_rate,

        ];

        $dataTradeUnits = [];
        if ($product->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($product->tradeUnits);
        }
        return [
            'imagesUploadedRoutes' => [
                'name'       => 'grp.org.shops.show.catalogue.products.all_products.images',
                'parameters' => [
                    'organisation' => $product->organisation->slug,
                    'shop'         => $product->shop->slug,
                    'product'      => $product->slug
                ]
            ],
            'stockImagesRoute' => [
                'name'  => 'grp.gallery.stock-images.index',
                'parameters'    => []
            ],
            'uploadImageRoute' => [
                'name'       => 'grp.models.product.images.store',
                'parameters' => [
                    'product'      => $product->id
                ]
            ],
            'attachImageRoute' => [
                'name'       => 'grp.models.org.product.images.attach',
                'parameters' => [
                    'organisation' => $product->organisation_id,
                    'product'      => $product->id
                ]
            ],
            'deleteImageRoute' => [
                'name'       => 'grp.models.org.product.images.delete',
                'parameters' => [
                    'organisation' => $product->organisation_id,
                    'product'      => $product->id
                ]
            ],
            'product' => ProductResource::make($product),
            'properties' => $properties,
            'parts' => OrgStocksResource::collection(GetOrgStocksInProduct::run($product))->resolve(),
            'stats'   => $product->stats,
            'trade_units' => $dataTradeUnits,
            'translation_box' => [
                'title' => __('Multi-language Translations'),
                'languages' => GetLanguagesOptions::make()->getExtraShopLanguages($product->shop->extra_languages),
                'save_route' => [
                    'name' => 'grp.models.trade-unit.translations.update',
                    'parameters' => [
                        'tradeUnit' => "",
                    ],
                ],
            ],
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }
}
