<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 23:12:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\GrpAction;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterFamiliesInMasterCollection extends GrpAction
{
    public function handle(MasterCollection $masterCollection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.code', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);




        $queryBuilder->join('master_collection_has_models', function ($join) {
            $join->on('master_product_categories.id', '=', 'master_collection_has_models.model_id')
                ->where('master_collection_has_models.model_type', '=', 'MasterProductCategory');

        });
        $queryBuilder->where('master_collection_has_models.master_collection_id', '=', $masterCollection->id);
        $queryBuilder->leftJoin('master_product_category_sales_intervals', 'master_product_category_sales_intervals.master_product_category_id', 'master_product_categories.id');
        $queryBuilder->leftJoin('master_product_category_ordering_intervals', 'master_product_category_ordering_intervals.master_product_category_id', 'master_product_categories.id');
        $queryBuilder->leftJoin('master_product_category_stats', 'master_product_categories.id', 'master_product_category_stats.master_product_category_id');



        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->select([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.description',
                'master_product_categories.created_at',
                'master_product_categories.image_id',
                'master_product_categories.updated_at',

            ])
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterCollection $masterCollection, $prefix = null, $action = true): Closure
    {
        return function (InertiaTable $table) use ($masterCollection, $prefix, $action) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->defaultSort('code')
                ->withEmptyState(
                    [
                        'title' => __("Collection doesn't have any families"),
                        'count' => 0,
                    ]
                )
                ->withGlobalSearch()
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            if ($action) {
                $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true);
            }


        };
    }
}
