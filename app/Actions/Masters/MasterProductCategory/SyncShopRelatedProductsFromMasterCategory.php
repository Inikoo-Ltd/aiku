<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 11:22:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\SyncProductCategoryRelatedProducts;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncShopRelatedProductsFromMasterCategory
{
    use AsAction;

    public function handle(MasterProductCategory $masterProductCategory): void
    {
        foreach ($masterProductCategory->productCategories as $productCategory) {
            $productIds = [];
            foreach ($masterProductCategory->relatedMasterAssets as $masterAsset) {
                $product = Product::where('shop_id', $productCategory->id)->where('master_product_id', $masterAsset->id)->first();
                if ($product) {
                    $productIds[] = $product->id;
                }
            }
            SyncProductCategoryRelatedProducts::make()->action($productCategory, $productIds);
        }
    }
}
