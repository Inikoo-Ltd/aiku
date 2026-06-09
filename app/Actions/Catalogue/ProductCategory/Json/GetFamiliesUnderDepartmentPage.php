<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-58m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\IrisAction;
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
            $query->where(function ($query) use ($value) {
                $query->where("sub_department.code", $value);
            });
        });

        return QueryBuilder::for(ProductCategory::class)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->leftJoin('product_categories as sub_department', 'product_categories.sub_department_id',  '=', 'sub_department.id')
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'product_categories.web_images',
                    'product_categories.image_id',
                    'webpages.canonical_url'
                ]
            )
            ->orderBy('product_categories.code')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.show_in_website', true)
            ->where('product_categories.shop_id', $parent->shop_id)
            ->where('product_categories.department_id', $parent->id)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$categorySearch])
            ->withPaginator($parent->code)
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
