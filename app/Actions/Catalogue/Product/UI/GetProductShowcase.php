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

class GetProductShowcase
{
    use AsObject;

    public function handle(Product $product): array
    {
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
                'name'       => 'grp.models.org.product.images.store',
                'parameters' => [
                    'organisation' => $product->organisation_id,
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
            'stats'   => $product->salesIntervals,
            'trade_units' => $dataTradeUnits,
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }
}
