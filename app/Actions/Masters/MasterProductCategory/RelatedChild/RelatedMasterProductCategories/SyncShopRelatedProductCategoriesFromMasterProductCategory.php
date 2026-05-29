<?php

/*
 * author Louis Perez
 * created on 29-05-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\RelatedChild\RelatedMasterProductCategories;

use App\Actions\Catalogue\ProductCategory\RelatedChild\RelatedProductCategories\SyncProductCategoryRelatedProductCategories;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncShopRelatedProductCategoriesFromMasterProductCategory
{
    use AsAction;

    public function handle(MasterProductCategory $masterProductCategory): void
    {
        foreach ($masterProductCategory->productCategories as $productCategory) {
            if (!data_get($productCategory->shop->settings, 'catalog.related_product_categories_follow_master', false)) {
                continue;
            }

            $productCategoriesId = [];
            foreach ($masterProductCategory->relatedMasterProductCategories as $relatedMasterProductCategory) {
                $relatedProductCategory = ProductCategory::where('shop_id', $productCategory->shop_id)->where('master_product_category_id', $relatedMasterProductCategory->id)->first();
                if ($relatedProductCategory) {
                    $productCategoriesId[] = $relatedProductCategory->id;
                }
            }
            SyncProductCategoryRelatedProductCategories::make()->action($productCategory, [
                'product_categories_id' => $productCategoriesId
            ]);
        }
    }
}
