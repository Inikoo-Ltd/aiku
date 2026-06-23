<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\WebBlockFamilyResourceForDepartmentWebpage;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetFamiliesUnderDepartmentPage extends IrisAction
{
    public function handle(ProductCategory $parent): LengthAwarePaginator
    {
        if ($parent->type !== ProductCategoryTypeEnum::DEPARTMENT) {
            abort(404);
        }

        $categorySearch = AllowedFilter::callback('category', function ($query, $value) {
            $query->where("sub_department.code", $value);
        });

        $collectionSearch = AllowedFilter::callback('collection', function ($query, $value) {
            $query
                ->join('collection_has_models as chm', function ($join) {
                    $join->on('chm.model_id', 'product_categories.id')
                        ->where('chm.model_type', class_basename(ProductCategory::class));
                })
                ->leftJoin('collections as c', 'c.id', 'chm.collection_id')
                ->where('c.code', $value);
        });

        return QueryBuilder::for(ProductCategory::class)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->leftJoin('product_categories as sub_department', 'product_categories.sub_department_id', '=', 'sub_department.id')
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'sub_department.code as sub_department_code',
                    'product_categories.name',
                    'product_categories.web_images',
                    'product_categories.image_id',
                    'product_categories.created_at',
                    'webpages.canonical_url'
                ]
            )
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])
            ->where('product_categories.show_in_website', true)
            ->where('product_categories.shop_id', $parent->shop_id)
            ->where('product_categories.department_id', $parent->id)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->defaultSort('-created_at')
            ->allowedSorts(['code', 'name', 'created_at'])
            ->allowedFilters([$categorySearch, $collectionSearch])
            ->withIrisPaginator(100)
            ->withQueryString();
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($productCategory);
    }

    public function jsonResponse(LengthAwarePaginator $familyList): AnonymousResourceCollection
    {
        return WebBlockFamilyResourceForDepartmentWebpage::collection($familyList);
    }
}
