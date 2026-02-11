<?php

/*
 * author Louis Perez
 * created on 03-02-2026-15h-19m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIrisCatalogue extends IrisAction
{
    private function queryForCollection()
    {
        $queryBuilder = QueryBuilder::for(Collection::class)
            ->where('collections.state', CollectionStateEnum::ACTIVE);

        return $queryBuilder
            ->where('collections.shop_id', $this->shop->id)
            ->select([
                    'collections.id',
                    'collections.slug',
                    'collections.code',
                    'collections.name',
                    'collections.web_images',
                    'collections.state',
                    'collections.description',
                    'collections.created_at',
                    'collections.updated_at',
            ])
            ->withCount(['families as number_current_families', 'products as number_current_products']);
    }

    private function queryForProductCategories(string $scope, ?string $parent = null, ?string $parentKey = null)
    {
        $queryBuilder = QueryBuilder::for(ProductCategory::class)
            ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE->value, ProductCategoryStateEnum::DISCONTINUING->value])
            ->where('product_categories.type', $scope);

        $parentColumnMap = [
            'department'     => 'department_id',
            'sub_department' => 'sub_department_id',
        ];

        $queryBuilder->when(isset($parentColumnMap[$parent]), function ($query) use ($scope, $parentColumnMap, $parent, $parentKey) {
            $query->where("product_categories.{$parentColumnMap[$parent]}", $parentKey);
        });

        $queryBuilder->when($parent === 'collection', function ($query) use ($parentKey) {
            $query->join('collection_has_models', function ($join) use ($parentKey) {
                $join->on('product_categories.id', '=', 'collection_has_models.model_id')
                    ->where('collection_has_models.model_type', class_basename(ProductCategory::class))
                    ->where('collection_has_models.collection_id', $parentKey);
            });
        });

        if ($scope != 'department') {
            $queryBuilder->leftJoin('product_categories as department', 'department.id', "product_categories.department_id");
        }

        if ($scope == 'family') {
            $queryBuilder->leftJoin('product_categories as sub_department', 'sub_department.id', "product_categories.sub_department_id");
        }

        $queryBuilder
            ->where('product_categories.shop_id', $this->shop->id)
            ->join('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->select([
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'product_categories.web_images',
                    'product_categories.state',
                    'product_categories.description',
                    'product_categories.created_at',
                    'product_categories.updated_at',
                    'product_category_stats.number_current_products',
            ]);

        return match($scope) {
            'department'        =>  $queryBuilder->addSelect([
                'product_category_stats.number_current_sub_departments',
                'product_category_stats.number_current_families',
            ]),
            'sub_department'    =>  $queryBuilder->addSelect([
                'product_category_stats.number_current_families',
                'department.code as department_code',
                'department.name as department_name',
            ]),
            default            =>  $queryBuilder->addSelect([
                'department.code as department_code',
                'department.name as department_name',
                'sub_department.code as sub_department_code',
                'sub_department.name as sub_department_name',
            ])
        };
    }

    private function queryForProducts(?string $parent = null, ?string $parentKey = null)
    {
        $queryBuilder = QueryBuilder::for(Product::class)
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value]);

        $parentColumnMap = [
            'department'     => 'department_id',
            'sub_department' => 'sub_department_id',
            'family'         => 'family_id',
        ];

        $queryBuilder->when(isset($parentColumnMap[$parent]), function ($query) use ($parentColumnMap, $parent, $parentKey) {
            $query->where("products.{$parentColumnMap[$parent]}", $parentKey);
        });

        $queryBuilder->when($parent === 'collection', function ($query) use ($parentKey) {
            $query->join('collection_has_models', function ($join) use ($parentKey) {
                $join->on('products.id', '=', 'collection_has_models.model_id')
                    ->where('collection_has_models.model_type', class_basename(Product::class))
                    ->where('collection_has_models.collection_id', $parentKey);
            });
        });

        return $queryBuilder
            ->where('products.shop_id', $this->shop->id)
            ->join('webpages', function ($join) {
                $join->on('products.id', 'webpages.model_id')
                    ->where('webpages.model_type', class_basename(Product::class));
            })
            ->leftJoin('product_categories as department', 'department.id', "products.department_id")
            ->leftJoin('product_categories as sub_department', 'sub_department.id', "products.sub_department_id")
            ->leftJoin('product_categories as family', 'family.id', "products.family_id")
            ->select([
                    'products.id',
                    'products.slug',
                    'products.code',
                    'products.name',
                    'products.state',
                    'products.description',
                    'products.created_at',
                    'products.updated_at',
                    'products.web_images',
                    'webpages.canonical_url',
                    'department.code as department_code',
                    'department.name as department_name',
                    'sub_department.code as sub_department_code',
                    'sub_department.name as sub_department_name',
                    'family.code as family_code',
                    'family.name as family_name',
            ]);
    }

    public function handle(array $modelData, ?string $prefix = null): LengthAwarePaginator
    {
        $parent = Arr::pull($modelData, 'parent');
        $parentKey = Arr::pull($modelData, 'parent_key');
        $scope = Arr::pull($modelData, 'scope');

        if (!$prefix) {
            $prefix = $scope;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($scope) {

            $tableName = match ($scope) {
                strtolower(class_basename(Collection::class))   => 'collections',
                strtolower(class_basename(Product::class))      => 'products',
                default                                         => 'product_categories',
            };

            $query->where(function ($query) use ($value, $tableName) {
                $query->whereAnyWordStartWith("{$tableName}.name", $value)
                    ->orWhereStartWith("{$tableName}.slug", $value);
            });
        });

        $queryBuilder = match ($scope) {
            strtolower(class_basename(Collection::class))   => $this->queryForCollection(),
            strtolower(class_basename(Product::class))      => $this->queryForProducts($parent, $parentKey),
            default                                         => $this->queryForProductCategories($scope, $parent, $parentKey),
        };

        InertiaTable::updateQueryBuilderParameters($prefix);

        $additionalSortableKeys = match ($scope) {
            'department' => [
                'number_current_families',
                'number_current_sub_departments',
                'number_current_products',
            ],
            'collection',
            'sub_department' => [
                'number_current_families',
                'number_current_products',
            ],
            'family' => [
                'number_current_products',
            ],
            default => [],
        };

        return $queryBuilder
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name', ...$additionalSortableKeys])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(string $scope, ?string $parent = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $scope, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table->withGlobalSearch();

            $table->column(key: 'image', label: '', type: 'avatar');
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                  ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if ($scope == 'sub_department') {
                $table->column(key: 'department_code', label:  __(key: 'Department'));
            }

            if ($scope == 'family') {
                $table->column(key: 'department_code', label:  __(key: 'Department'));
                $table->column(key: 'sub_department', label: __(key: 'Sub Department'));
            }

            if ($scope == 'product') {
                $table->column(key: 'department_code', label:  __(key: 'Department'));
                $table->column(key: 'sub_department', label:  __(key: 'Sub Department'));
                $table->column(key: 'family', label:  __(key: 'Family'));
            }

            $columns = match ($scope) {
                strtolower(class_basename(Product::class)) => [],
                strtolower(class_basename(Collection::class)) => [
                    ['key' => 'number_current_families', 'label' => __('Families'), 'sortable' => true],
                    ['key' => 'number_current_products', 'label' => __('Products'), 'sortable' => true],
                ],
                'department' => [
                    ['key' => 'number_current_sub_departments', 'label' => __('Sub-departments'), 'sortable' => true],
                    ['key' => 'number_current_families', 'label' => __('Families'), 'sortable' => true],
                    ['key' => 'number_current_products', 'label' => __('Products'), 'sortable' => true],
                ],
                'sub_department' => [
                    ['key' => 'number_current_families', 'label' => __('Families'), 'sortable' => true],
                    ['key' => 'number_current_products', 'label' => __('Products'), 'sortable' => true],
                ],
                'family'      => [
                    ['key' => 'number_current_products', 'label' => __('Products'), 'sortable' => true],
                ],
                default => [],
            };


            foreach ($columns as $column) {
                $table->column(key: $column['key'], label: $column['label'], tooltip: __('current ' . strtolower($column['label'])), sortable: $column['sortable']);
            }

            $table->column(key: 'url', label: __('Go To Url'), align: 'right');
        };
    }

    public function action(array $modelData, ActionRequest $request): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisation($request);

        return $this->handle($modelData);
    }
}
