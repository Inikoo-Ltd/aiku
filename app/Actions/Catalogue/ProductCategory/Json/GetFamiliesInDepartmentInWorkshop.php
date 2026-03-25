<?php

/*
 * author Louis Perez
 * created on 17-03-2026-17h-20m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\WorkshopSubDepartmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetFamiliesInDepartmentInWorkshop extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithDepartmentSubNavigation;

    private Shop|ProductCategory|Organisation $parent;


    public function asController(ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($department->shop, $request);

        return $this->handle(department: $department);
    }


    public function handle(ProductCategory $department, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        $queryBuilder->where('product_categories.department_id', $department->id);
        $queryBuilder->where('product_categories.type', ProductCategoryTypeEnum::FAMILY);


        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.description_extra',
                'product_categories.description_title',
                'product_categories.created_at',
                'product_categories.updated_at',
                'product_categories.web_images',

            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $subDepartment): AnonymousResourceCollection
    {
        return WorkshopSubDepartmentsResource::collection($subDepartment);
    }
}
