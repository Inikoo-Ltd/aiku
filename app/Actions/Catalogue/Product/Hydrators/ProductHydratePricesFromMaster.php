<?php

/*
 * Author Louis Perez
 * Created on 22-07-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydratePricesFromMaster
{
    use AsAction;

    public function handle(ProductCategory|Shop $parent)
    {
        // Disallow sub-department / department from calling this, just a small guard
        if ($parent instanceof ProductCategory && $parent->type != ProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $products   = Product::query();
        $shop       = $parent;
        
        if ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
            $products->where('family_id', $parent->id);
        }

        $products->where('shop_id', $shop->id);

        $products
            ->with('masterProduct')
            ->chunkById(500, function ($chunks) use ($shop) {
                foreach($chunks as $product) {
                    MasterAssetHydrateMasterPricesRRPtoChild::run($product->masterProduct, $shop);
                }
            });
    }
}
