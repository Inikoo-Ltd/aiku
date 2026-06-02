<?php

/*
 * author Louis Perez
 * created on 29-05-2026-13h-11m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetProductCategorySiblingRecommendation extends OrgAction
{
    public function handle(ProductCategory $productCategory, bool $includeMasterRelatedProductCategory = false): Collection
    {
        $localRecommendations = DB::table('product_category_has_related_product_categories')
            ->leftJoin('product_categories', 'related_product_category_id', 'product_categories.id')
            ->where('product_category_id', $productCategory->id)
            ->whereNotNull('product_categories.id')
            ->orderBy('product_category_has_related_product_categories.position')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.web_images',
                DB::raw('product_category_has_related_product_categories.position as position'),
            ])->get();

        if (!$productCategory->master_product_category_id) {
            return $localRecommendations;
        }

        $masterRecommendations = DB::table('master_product_category_has_related_product_categories')
            ->leftJoin('master_product_categories', 'related_master_product_category_id', 'master_product_categories.id')
            ->leftJoin('product_categories', function ($join) use ($productCategory) {
                $join->on('product_categories.master_product_category_id', '=', 'master_product_category_has_related_product_categories.related_master_product_category_id')
                    ->where('product_categories.shop_id', '=', $productCategory->shop_id);
            })
            ->where('master_product_category_has_related_product_categories.master_product_category_id', $productCategory->master_product_category_id)
            ->whereNotNull('product_categories.id')
            ->orderBy('master_product_category_has_related_product_categories.position')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.web_images',
                DB::raw('master_product_category_has_related_product_categories.position as position'),
            ])->get();


        if (!$includeMasterRelatedProductCategory) {
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
