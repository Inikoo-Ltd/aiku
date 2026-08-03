<?php

/*
 * author Louis Perez
 * created on 06-05-2026-11h-27m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInRecommendation extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory, $stockMode = 'in_stock', bool $topSeller = false): LengthAwarePaginator|Collection
    {
        $website = $productCategory->shop->website;

        $queryBuilder = $this->getBaseQuery($stockMode, $topSeller);


        $queryBuilder
            ->where(function ($query) {
                $query
                    ->whereNull('products.variant_id')
                    ->orWhere('products.is_variant_leader', true);
            });

        $queryBuilder->select(
            $this->getSelect([
                DB::raw('products.variant_id IS NOT NULL as is_variant'),
            ])
        );

        $perPage = data_get($website->settings, 'recommender_web_block.description_has_overview', 100);

        $relatedProduct = $productCategory->relatedProducts()->get();
        $queryBuilder->whereIn('products.id', $relatedProduct->pluck('id'));

        if ($perPage < 10) {
            return $this->getDataHardLimit($queryBuilder, $perPage);
        } else {
            return $this->getData($queryBuilder, $perPage);
        }
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }


}
