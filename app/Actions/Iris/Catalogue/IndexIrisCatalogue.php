<?php

/*
 * author Louis Perez
 * created on 03-02-2026-15h-19m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisDepartmentResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIrisCatalogue extends IrisAction
{

    private function queryForProductCategories(string $scope, ?string $parent = null, ?string $parentKey = null)
    {
        $queryBuilder = QueryBuilder::for(ProductCategory::class)
            ->whereIn('product_categories.state', [ProductCategoryStateEnum::ACTIVE->value, ProductCategoryStateEnum::DISCONTINUING->value])
            ->where('product_categories.type', $scope);
        
        $parentColumnMap = [
            'department'     => 'department_id',
            'sub_department' => 'sub_department_id',
        ];

        $queryBuilder->when(isset($parentColumnMap[$parent]), function ($query) use ($parentColumnMap, $parent, $parentKey) {
            $query->where("product_categories.{$parentColumnMap[$parent]}", $parentKey);
        });

        $queryBuilder->when($parent === 'collection', function ($query) use ($parentKey) {
            $query->join('collection_has_models', function ($join) use ($parentKey) {
                $join->on('product_categories.id', '=', 'collection_has_models.model_id'
                )
                ->where('collection_has_models.model_type', ProductCategory::class)
                ->where('collection_has_models.collection_id', $parentKey);
            });
        });

        $queryBuilder
            ->where('product_categories.shop_id', $this->shop->id)
            ->join('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->select([
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'product_categories.state',
                    'product_categories.description',
                    'product_categories.created_at',
                    'product_categories.updated_at',
                    'product_category_stats.number_current_products',
            ]);   
        
        return match($scope) { 
            'department'        =>  $queryBuilder->addSelect(['product_category_stats.number_current_sub_departments', 'product_category_stats.number_current_families']),
            'sub_department'    =>  $queryBuilder->addSelect('product_category_stats.number_current_families'),
            default            =>  $queryBuilder
        };
    }

    private function queryForProducts(string $parent, string $parentKey)
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
                    ->where('collection_has_models.model_type', Product::class)
                    ->where('collection_has_models.collection_id', $parentKey);
            });
        });

        if ($parent == 'collection') {
            $queryBuilder->join('collection_has_models', function ($join) use ($parentKey) {
                $join->on('products.id', 'collection_has_model.model_id')
                    ->where('collection_has_model.model_type', 'Product')
                    ->where('collection_has_model.collection_id', $parentKey);
            });
        }

        return $queryBuilder
            ->where('products.shop_id', $this->shop->id)
            ->join('webpages', function ($join) {
                $join->on('products.id', 'webpages.model_id')
                    ->where('webpages.model_type', 'Product');
            })
            ->select([
                    'products.id',
                    'products.slug',
                    'products.code',
                    'products.name',
                    'products.state',
                    'products.description',
                    'products.created_at',
                    'products.updated_at',
                    'webpages.canonical_url'
            ]);
    }

    public function handle(array $modelData, ?string $prefix = null): LengthAwarePaginator
    {
        $parent = Arr::pull($modelData, 'parent');
        $parentKey = Arr::pull($modelData, 'parentKey');
        $scope = Arr::pull($modelData, 'scope');

        $queryBuilder = match ($scope) {
            strtolower(class_basename(Collection::class)) => QueryBuilder::for(Collection::class),
            strtolower(class_basename(Product::class))    => $this->queryForProducts($parent, $parentKey),
            default                           => $this->queryForProductCategories($scope, $parent, $parentKey),
        };

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(string $scope, string $parent, $prefix = null): Closure
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

            $columns = match ($scope) {
                strtolower(class_basename(Product::class)) => [],
                strtolower(class_basename(Collection::class)) => [],
                'department' => [
                    ['key' => 'number_current_sub_departments', 'label' => __('Sub-departments')],
                    ['key' => 'number_current_families', 'label' => __('Families')],
                    ['key' => 'number_current_products', 'label' => __('Products')],
                ],
                'sub_department' => [
                    ['key' => 'number_current_families', 'label' => __('Families')],
                    ['key' => 'number_current_products', 'label' => __('Products')],
                ],
                'family'      => [
                    ['key' => 'number_current_products', 'label' => __('Products')],
                ],
                default => [],
            };
            
            foreach ($columns as $column) {
                $table->column(key: $column['key'], label: $column['label'], tooltip: __('current ' . strtolower($column['label'])));
            };
        };
    }

    public function action(array $modelData, ActionRequest $request): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisation($request);

        return $this->handle($modelData);
    }
}
