<?php

/*
 * author Louis Perez
 * created on 29-05-2026-13h-41m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductCategoriesInRecommendation extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory): LengthAwarePaginator|Collection
    {
        $relatedProductCategoriesId = $productCategory->relatedProductCategories->pluck('id');

        $relatedProductCategories = QueryBuilder::for(ProductCategory::class)
            ->whereIn('product_categories.id', $relatedProductCategoriesId)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', 'ProductCategory');
            })
            ->select([
                'product_categories.code',
                'product_categories.web_images',
                'product_categories.offers_data',
                'name',
                'image_id',
                'webpages.url',
                'webpages.canonical_url',
                'title'
            ])
            ->selectRaw('\''.request()->path().'\' as parent_url')
            ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
            ->where('show_in_website', true)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->whereNull('webpages.deleted_at')
            ->get();

        return $relatedProductCategories;
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }


}
