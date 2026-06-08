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

class GetFamiliesUnderDepartmentPage extends IrisAction
{
    public function handle(ProductCategory $parent): LengthAwarePaginator
    {
        return QueryBuilder::for(ProductCategory::class)
            ->when(
                $parent->type === ProductCategoryTypeEnum::DEPARTMENT,
                fn ($q) => $q->where('department_id', $parent->id),
                fn ($q) => $q->where('sub_department_id', $parent->id),
            )
            ->leftjoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
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
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->allowedSorts(['code', 'name'])
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
