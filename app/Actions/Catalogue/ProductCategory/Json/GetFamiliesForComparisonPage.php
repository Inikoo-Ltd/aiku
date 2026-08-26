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
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetFamiliesForComparisonPage extends IrisAction
{
    public function handle(ProductCategory $family, Website $website): LengthAwarePaginator
    {
        $template = request()->input('template') ?: Arr::get($family->category_comparison, 'template');

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });

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
                    'webpages.canonical_url',
                ]
            )
            ->selectRaw('(product_categories.id = ?) as is_current', [$family->id])
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])
            ->where('product_categories.show_in_website', true)
            ->where('product_categories.shop_id', $family->shop_id)
            ->whereNull('product_categories.deleted_at')
            ->whereNotNull('webpages.id')
            ->where('webpages.website_id', $website->id)
            ->where('webpages.state', WebpageStateEnum::LIVE->value);

        if ($template) {
            $query->where(
                'product_categories.category_comparison->template',
                '=',
                "$template"
            );
        }

        return $query
            ->allowedFilters([$globalSearch])
            ->defaultSort('-category_comparison')
            ->allowedSorts(['code', 'name', 'created_at'])
            ->withIrisPaginator(500)
            ->withQueryString();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($productCategory, $this->website);
    }

    public function inWebsite(Website $website, ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $request->merge(['website' => $website]);
        $this->initialisation($request);

        return $this->handle($productCategory, $website);
    }

    public function jsonResponse(LengthAwarePaginator $familyList): AnonymousResourceCollection
    {
        return FamiliesForCategoryComparison::collection($familyList);
    }
}
