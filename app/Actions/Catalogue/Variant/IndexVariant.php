<?php

/*
 * author Louis Perez
 * created on 16-12-2025-15h-36m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Variant;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Closure;

class IndexVariant extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('variants.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Variant::class);

        if ($parent instanceof ProductCategory) {
            if ($parent->type == ProductCategoryTypeEnum::FAMILY) {
                $queryBuilder->where('variants.family_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $queryBuilder->where('variants.sub_department_id', $parent->id);
            } elseif ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('variants.department_id', $parent->id);
            } else {
                abort(419);
            }
        }

        return $queryBuilder
         ->leftJoin('products', 'products.id', '=', 'variants.leader_id')
         ->leftJoin('master_variants', 'master_variants.id', '=', 'variants.master_variant_id')
         ->defaultSort('variants.code')
         ->select([
             'variants.id',
             'variants.slug',
             'variants.code',
             'variants.leader_id',
             'variants.number_minions',
             'variants.number_dimensions',
             'variants.number_used_slots',
             'variants.number_used_slots_for_sale',
             'variants.data',
             'products.id as leader_product_id',
             'products.name as leader_product_name',
             'products.code as leader_product_code',
             'products.slug as leader_product_slug',
             'master_variants.code as parent_code',
             'master_variants.slug as parent_slug',
         ])
         ->allowedSorts([
             'code',
             'leader_product_name',
         ])
         ->allowedFilters([$globalSearch])
         ->withPaginator($prefix, tableName: request()->route()->getName())
         ->withQueryString();
    }

    public function tableStructure(ProductCategory $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'ProductCategory' => [
                            'title' => __("No variants found under this family"),
                            'count' => 0,
                        ],
                        default => null
                    }
                )
                ->withLabelRecord([__('variant'),__('variants')])
                ->withGlobalSearch();

            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'leader_product_name', label: __('Leader Product'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_dimensions', label: __('Options'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_used_slots', label: __('No. of Slots'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_used_slots_for_sale', label: __('No. of Slots Used (For Sale enabled)'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
        };
    }
}
