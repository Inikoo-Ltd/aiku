<?php

/*
 * author Louis Perez
 * created on 16-12-2025-15h-36m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterVariant;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Closure;

class IndexMasterVariant extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_variants.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterVariant::class);

        if ($parent instanceof MasterProductCategory) {
            if ($parent->type == MasterProductCategoryTypeEnum::FAMILY) {
                $queryBuilder->where('master_variants.master_family_id', $parent->id);
            } elseif ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $queryBuilder->where('master_variants.master_sub_department_id', $parent->id);
            } elseif ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('master_variants.master_department_id', $parent->id);
            } else {
                abort(419);
            }
        }

        return $queryBuilder
         ->leftJoin('master_assets', 'master_assets.id', '=', 'master_variants.leader_id')
         ->defaultSort('master_variants.code')
         ->select([
             'master_variants.id',
             'master_variants.slug',
             'master_variants.code',
             'master_variants.leader_id',
             'master_variants.number_minions',
             'master_variants.number_dimensions',
             'master_variants.number_used_slots',
             'master_variants.number_used_slots_for_sale',
             'master_variants.data',
             'master_assets.id as leader_product_id',
             'master_assets.name as leader_product_name',
             'master_assets.code as leader_product_code',
             'master_assets.slug as leader_product_slug',
         ])
         ->allowedSorts([
             'code',
             'leader_product_name',
         ])
         ->allowedFilters([$globalSearch])
         ->withPaginator($prefix, tableName: request()->route()->getName())
         ->withQueryString();
    }

    public function tableStructure(MasterProductCategory $parent, $prefix = null): Closure
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
                        'MasterProductCategory' => [
                            'title' => __("No master variants found under this master family"),
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
