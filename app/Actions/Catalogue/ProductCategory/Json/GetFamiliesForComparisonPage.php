<?php

/*
 * Author Louis Perez
 * Created on 26-08-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\FamiliesForCategoryComparison;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetFamiliesForComparisonPage extends IrisAction
{
    public function handle(ProductCategory $family): LengthAwarePaginator
    {
        $templateFilter = request()->input('template');

        $query = QueryBuilder::for(ProductCategory::class)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'product_categories.category_comparison',
                    'product_categories.created_at',
                    'product_categories.web_images',
                ]
            )
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])
            ->whereNot('product_categories.id', $family->id)
            ->where('product_categories.show_in_website', true)
            ->where('product_categories.shop_id', $family->shop_id)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value);

        if ($templateFilter) {
            $query->where(
                'product_categories.category_comparison->template',
                '=',
                "{$templateFilter}"
            );
        }

        return $query
            ->allowedFilters([$templateFilter])
            ->defaultSort('-category_comparison')
            ->allowedSorts(['code', 'name', 'created_at'])
            ->withIrisPaginator(500)
            ->withQueryString();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($productCategory);
    }

    public function jsonResponse(LengthAwarePaginator $familyList): AnonymousResourceCollection
    {
        return FamiliesForCategoryComparison::collection($familyList);
    }
}
