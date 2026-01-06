<?php

/*
 * author Louis Perez
 * created on 06-01-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterVariant;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterProductsInMasterVariant extends OrgAction
{
    public function handle(MasterVariant $masterVariant, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereStartWith('master_assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class);
        $queryBuilder->where('master_variant_id', $masterVariant->id);
        $queryBuilder->leftJoin('master_variants', 'master_variants.id', 'master_assets.master_variant_id');


        $queryBuilder
            ->defaultSort('master_assets.code')
            ->select([
                'master_assets.id',
                'master_assets.status',
                'master_assets.code',
                'master_assets.name',
                'master_assets.price',
                'master_assets.created_at',
                'master_assets.updated_at',
                'master_assets.slug',
                'master_assets.web_images',
                'master_assets.is_variant_leader',
                'master_variants.slug as variant_slug'
            ]);

        return $queryBuilder->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("There is no master products"),
                    ]
                );
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'is_variant_leader', label: ['fal', 'fa-star-half-alt'], type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
