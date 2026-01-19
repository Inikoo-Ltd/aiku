<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Catalogue;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisDepartmentResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIrisDepartments extends RetinaAction
{
    private Shop|Collection $parent;
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        $this->parent = $this->shop;

        return $this->handle(parent: $this->shop);
    }

    public function handle(Shop|ProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.slug', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);
        $queryBuilder->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE->value, ProductCategoryStateEnum::DISCONTINUING->value]);


        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.updated_at',
                'product_category_stats.number_current_families',
                'product_category_stats.number_current_products',
                'product_category_stats.number_current_sub_departments',
                'product_category_stats.number_current_collections',
            ])
            ->where('product_categories.type', ProductCategoryTypeEnum::DEPARTMENT)
            ->where('product_categories.shop_id', $parent->id)
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|ProductCategory|Collection $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }
            $table->withGlobalSearch();


            $table->column(key: 'image', label: '', type: 'avatar');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                  ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);


            if (class_basename($parent) != 'Collection') {
                $table->column(key: 'number_current_sub_departments', label: __('Sub-departments'), tooltip: __('current sub departments'));
                $table->column(key: 'number_current_collections', label: __('Collections'), tooltip: __('current collections'));
                $table->column(key: 'number_current_families', label: __('Families'), tooltip: __('current families'))
                    ->column(key: 'number_current_products', label: __('Products'), tooltip: __('current products'));
            }

            if (class_basename($parent) == 'Collection') {
                $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return IrisDepartmentResource::collection($departments);
    }
}
