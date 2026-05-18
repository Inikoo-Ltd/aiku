<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetProductCategoryRecomendation extends OrgAction
{
    public function handle(ProductCategory $productCategory, bool $includeMasterRelatedProducts = false): Collection
    {
        $localRecommendations = DB::table('product_category_has_related_products')
            ->leftJoin('products', 'product_id', 'products.id')
            ->where('product_category_id', $productCategory->id)
            ->whereNotNull('products.id')
            ->orderBy('product_category_has_related_products.position')
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.web_images',
                DB::raw('product_category_has_related_products.position as position'),
            ])->get();

        if (!$productCategory->master_product_category_id) {
            return $localRecommendations;
        }

        $masterRecommendations = DB::table('master_product_category_has_related_assets')
            ->leftJoin('products', function ($join) use ($productCategory) {
                $join->on('products.master_product_id', '=', 'master_product_category_has_related_assets.master_asset_id')
                    ->where('products.shop_id', '=', $productCategory->shop_id);
            })
            ->where('master_product_category_has_related_assets.master_product_category_id', $productCategory->master_product_category_id)
            ->whereNotNull('products.id')
            ->orderBy('master_product_category_has_related_assets.position')
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.web_images',
                DB::raw('master_product_category_has_related_assets.position as position'),
            ])->get();

        if (!$includeMasterRelatedProducts) {
            return $localRecommendations;
        }

        $mergedRecommendations = $localRecommendations->values();
        $existingProductIds = $localRecommendations->pluck('id')->all();
        $nextPosition = (int) ($localRecommendations->max('position') ?? 0);

        foreach ($masterRecommendations as $masterRecommendation) {
            if (\in_array($masterRecommendation->id, $existingProductIds, true)) {
                continue;
            }

            $nextPosition++;
            $masterRecommendation->position = $nextPosition;
            $mergedRecommendations->push($masterRecommendation);
        }

        return $mergedRecommendations;
    }
}
