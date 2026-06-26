<?php

/*
 * author Louis Perez
 * created on 02-06-2026-11h-30m
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

class GetIrisProductCategoriesInRelated extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(array $relatedProductCategoriesId): LengthAwarePaginator|Collection
    {
        $relatedProductCategories = QueryBuilder::for(ProductCategory::class)
            ->whereIn('product_categories.id', $relatedProductCategoriesId)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', 'ProductCategory')
                    ->where('webpages.layout_style', 'main_page')
                    ->where('webpages.state', WebpageStateEnum::LIVE->value);
            })
            ->select([
                'product_categories.id',
                'product_categories.type',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.description',
                'product_categories.web_images',
                'webpages.url as shorthand_url',
                'webpages.canonical_url',
            ])
            ->selectRaw('\''.request()->path().'\' as parent_url')
            ->where('show_in_website', true)
            ->whereNotNull('webpages.id')
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])
            ->whereNull('product_categories.deleted_at')
            ->whereNull('webpages.deleted_at')
            ->get();

        return $relatedProductCategories;
    }
}
