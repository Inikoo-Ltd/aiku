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
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Http\Resources\Catalogue\IrisFamilyResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIrisFamilies extends RetinaAction
{
    private Shop|ProductCategory $parent;

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
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        $queryBuilder->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE->value, ProductCategoryStateEnum::DISCONTINUING->value]);



        if (class_basename($parent) == 'Shop') {
            $queryBuilder->where('product_categories.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'ProductCategory') {
            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('product_categories.department_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $queryBuilder->where('product_categories.sub_department_id', $parent->id);
            } else {
                abort(419);
            }
        }


        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.image_id',
                'product_categories.updated_at',
                'departments.slug as department_slug',
                'departments.code as department_code',
                'departments.name as department_name',
                'sub_departments.slug as sub_department_slug',
                'sub_departments.code as sub_department_code',
                'sub_departments.name as sub_department_name',
                'product_category_stats.number_current_products',
            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->leftjoin('product_categories as departments', 'departments.id', 'product_categories.department_id')
            ->leftjoin('product_categories as sub_departments', 'sub_departments.id', 'product_categories.sub_department_id')
            ->allowedSorts(['code', 'name', 'state', 'shop_code', 'department_code', 'number_current_products', 'sub_department_name', 'department_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop|ProductCategory $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Shop', 'ProductCategory' => [
                            'title' => __("No families found"),
                            'count' => $parent->stats->number_families,
                        ],
                        default => null
                    }
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon', sortable: true)
                ->withModelOperations($modelOperations);

            $table->column(key: 'image', label: __('Image'), type: 'icon');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sub_department_name', label: __('Sub department'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'department_name', label: __('Department'), canBeHidden: false, sortable: true, searchable: true);


            if (class_basename($parent) != 'Collection') {
                $table->column(key: 'number_current_products', label: __('Current products'), canBeHidden: false, sortable: false, searchable: false);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return IrisFamilyResource::collection($families);
    }

}
